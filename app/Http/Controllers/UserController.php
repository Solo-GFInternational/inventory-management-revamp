<?php

namespace App\Http\Controllers;

use App\Mail\UserAccountCredentials;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\TemporaryPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Settings > Users. Create and edit happen in modals on the list page.
 * Creating a user (or reusing a deactivated account's email, which
 * reactivates it) generates a temporary password and emails it to them
 * (App\Mail\UserAccountCredentials); the admin never sees it.
 * Accounts are never deleted: "delete" deactivates (active_yn = false) and
 * reactivate restores them. Deactivated accounts cannot sign in.
 */
class UserController extends Controller
{
    public const ROLES = ['admin' => 'Admin', 'user' => 'User'];

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';
        $search = trim((string) $request->query('q'));

        $users = User::query()
            ->when($status === 'active', fn ($query) => $query->where('active_yn', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active_yn', false))
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.$search.'%';
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->get();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => self::ROLES,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Only active accounts block an email (as the filtered index
            // ux_users_email does): a deactivated account's email is reused
            // by reactivating that account (below). The model class, not
            // 'security.users': a dotted table string is read as connection.table.
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->where('active_yn', true)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
        ], [
            'email.unique' => 'An active account already uses this email.',
        ]);

        // Random, and only ever in the sign-in email to the person themselves:
        // never typed or seen by the admin, never flashed or logged.
        $password = TemporaryPassword::generate();

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($password),
        ];

        // The index only stops two active accounts sharing an email, but adding
        // a deactivated person back restores their account (and its history)
        // rather than creating a second row for the same person.
        $user = User::where('email', $data['email'])->where('active_yn', false)->orderByDesc('updated_at')->first();
        $reactivated = $user !== null;

        if ($reactivated) {
            $user->fill($attributes);
            $user->active_yn = true;
            $user->save();
        } else {
            $user = User::create($attributes);
        }

        $done = $reactivated ? 'Account reactivated' : 'Account created';

        // The account is kept even when the email fails; the admin is told so
        // they can deactivate and add it again (a new password is emailed) or
        // set one with Edit and pass it on themselves.
        if (! $this->emailCredentials($user, $password, $reactivated)) {
            return redirect()->route('users.index')->with([
                'status_title' => 'Sign-in email not sent',
                'error' => "{$done} for {$user->name}, but the email with their sign-in details could not be sent to {$user->email}. "
                    .'Deactivate the account and add it again to send a new password, or set a password with Edit and give it to them another way.',
            ]);
        }

        return redirect()->route('users.index')->with([
            'status_title' => $reactivated ? 'User reactivated' : 'User created',
            'status' => "{$done} — sign-in details emailed to {$user->email}.",
        ]);
    }

    /**
     * Queues the sign-in details email (sent during the request while
     * QUEUE_CONNECTION=sync) and notes it on the audit trail. Returns false,
     * after reporting the error, when sending failed (e.g. an SMTP error).
     * With a real queue a later failure lands in failed_jobs instead.
     */
    private function emailCredentials(User $user, string $password, bool $reactivated): bool
    {
        try {
            Mail::to($user)->queue(new UserAccountCredentials($user, $password, $reactivated));
        } catch (Throwable $e) {
            report($e);

            AuditTrail::write(fn () => activity('user')->performedOn($user)
                ->event('credentials email failed')
                ->log('Sign-in details email could not be sent'));

            return false;
        }

        AuditTrail::write(fn () => activity('user')->performedOn($user)
            ->event('credentials emailed')
            ->log('Sign-in details emailed'));

        return true;
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Unlike create, deactivated accounts count too: taking a
            // deactivated account's email here would leave two rows for it,
            // and that account could no longer be reactivated.
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
        ], [
            'email.unique' => 'Another account, active or deactivated, already uses this email.',
        ]);

        // An admin demoting themselves could leave nobody able to manage users.
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'You cannot remove the admin role from your own account.']);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

        // Left blank, the password is left untouched.
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('users.index')->with([
            'status_title' => 'User updated',
            'status' => "{$user->name}'s details have been saved.",
        ]);
    }

    /**
     * Deactivates the account (active_yn = false); the row is kept.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->active_yn = false;
        $user->save();

        return back()->with([
            'status_title' => 'User deactivated',
            'status' => "{$user->name} can no longer sign in. Reactivate them from the Inactive list.",
        ]);
    }

    public function reactivate(User $user): RedirectResponse
    {
        // Only one active account per email (ux_users_email); data from before
        // the filtered index may hold a second row with this email.
        $clash = User::where('email', $user->email)->where('active_yn', true)->whereKeyNot($user->getKey())->exists();

        if ($clash) {
            return back()->with('error', "Another active account already uses {$user->email}, so {$user->name} can't be reactivated. Change one of the emails first.");
        }

        $user->active_yn = true;
        $user->save();

        return back()->with([
            'status_title' => 'User reactivated',
            'status' => "{$user->name} can sign in again.",
        ]);
    }
}

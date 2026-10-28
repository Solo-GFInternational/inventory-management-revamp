<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings > Users. Create and edit happen in modals on the list page.
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
            // Only active accounts block an email: a deactivated account's
            // email is reused by reactivating that account (below).
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->where('active_yn', true)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ], [
            'email.unique' => 'An active account already uses this email.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
        ];

        // The email column is unique across all rows, so adding a deactivated
        // person back restores their account rather than failing or duplicating.
        $existing = User::where('email', $data['email'])->where('active_yn', false)->first();

        if ($existing) {
            $existing->fill($attributes);
            $existing->active_yn = true;
            $existing->save();

            return redirect()->route('users.index')->with([
                'status_title' => 'User reactivated',
                'status' => "{$existing->name}'s deactivated account has been reactivated with the details and password you entered.",
            ]);
        }

        $user = User::create($attributes);

        return redirect()->route('users.index')->with([
            'status_title' => 'User created',
            'status' => "{$user->name} can now sign in with {$user->email} and the password you set.",
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
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
        $user->active_yn = true;
        $user->save();

        return back()->with([
            'status_title' => 'User reactivated',
            'status' => "{$user->name} can sign in again.",
        ]);
    }
}

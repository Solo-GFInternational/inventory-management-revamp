<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'Your profile has been updated.');
    }

    /**
     * Self-service account deletion is switched off: accounts are created,
     * deactivated and reactivated by an administrator (Settings → Users),
     * and rows are never hard-deleted. The route is kept only so old links
     * or forms get a clear refusal instead of deleting anything.
     */
    public function destroy(): never
    {
        abort(403, 'Accounts are managed by an administrator.');
    }
}

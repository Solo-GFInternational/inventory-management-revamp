{{--
    The user form's fields, shared by the "New user" and "Edit user" modals.
      $targetUser  the account being edited, or null when creating
      $roles       role value => label (UserController::ROLES)
      $prefix      prefix for the field ids, unique per form on the page
      $useOld      whether to repopulate from old() input — only the form that
                   was actually submitted should, so a failed edit of one user
                   doesn't leak its input into every other row's modal
--}}
@php
    $value = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;
    $editingSelf = $targetUser !== null && $targetUser->is(auth()->user());
    // A new account starts as a plain user, so a hurried "Create" never hands out admin.
    $selectedRole = $value('role', $targetUser?->role ?? 'user');
@endphp

<div class="space-y-6">
    <div>
        <label for="{{ $prefix }}name" class="gf-label">Name</label>
        <input id="{{ $prefix }}name" name="name" type="text" value="{{ $value('name', $targetUser?->name) }}" required maxlength="255"
               autocomplete="off" class="gf-input">
    </div>

    <div>
        <label for="{{ $prefix }}email" class="gf-label">Email</label>
        <input id="{{ $prefix }}email" name="email" type="email" value="{{ $value('email', $targetUser?->email) }}" required maxlength="255"
               autocomplete="off" class="gf-input">
        @if ($targetUser === null)
            <p class="gf-hint">If this email belongs to a deactivated account, that account is reactivated with these details and a new temporary password.</p>
        @endif
    </div>

    <div>
        <label for="{{ $prefix }}role" class="gf-label">Role</label>
        <select id="{{ $prefix }}role" name="role" required class="gf-input select-arrow">
            @foreach ($roles as $roleValue => $roleLabel)
                <option value="{{ $roleValue }}" @selected($selectedRole === $roleValue)>{{ $roleLabel }}</option>
            @endforeach
        </select>
        @if ($editingSelf)
            <p class="gf-hint">You cannot remove the admin role from your own account.</p>
        @else
            <p class="gf-hint">Admins approve inventory changes and manage users; users record items and their edits go for approval.</p>
        @endif
    </div>

    @if ($targetUser === null)
        {{-- No password box: one is generated (App\Support\TemporaryPassword) and only appears in their email. --}}
        <div class="flex items-start gap-3 rounded-lg bg-primary-50 px-4 py-3 text-sm text-primary-800 ring-1 ring-primary-600/20">
            <x-icon name="envelope" class="mt-px size-5 shrink-0 text-primary-600" />
            <p>
                A temporary password is created for them and emailed to this address with the sign-in link.
                Nobody else sees it. They can change it under Your profile after signing in.
            </p>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach (['password' => 'New password', 'password_confirmation' => 'Confirm password'] as $field => $label)
                <div>
                    <label for="{{ $prefix }}{{ $field }}" class="gf-label">
                        {{ $label }} <span class="font-normal text-gray-400">(optional)</span>
                    </label>
                    <div class="relative" data-password-field>
                        <input id="{{ $prefix }}{{ $field }}" name="{{ $field }}" type="password" minlength="8"
                               autocomplete="new-password" class="gf-input pe-11">
                        <button type="button" data-password-toggle aria-label="Show password"
                                class="absolute inset-y-0 end-0 flex items-center px-3 text-gray-400 hover:text-gray-500">
                            <x-icon name="eye" data-icon-show />
                            <x-icon name="eye-slash" data-icon-hide hidden />
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="gf-hint -mt-4">
            At least 8 characters. Leave both blank to keep the current password. Changing it here doesn't email the
            user &mdash; tell them the new password yourself.
        </p>
    @endif
</div>

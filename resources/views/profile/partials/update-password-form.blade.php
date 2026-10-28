{{--
    Errors go to the updatePassword bag (Auth\PasswordController), which the
    layout's SweetAlert doesn't read, so they're listed in the card.
--}}
<form method="POST" action="{{ route('password.update') }}" data-processing="Updating your password…">
    @csrf
    @method('PUT')

    <x-card title="Update password" icon="key" subtitle="Use at least 8 characters.">
        <div class="p-6">
            <x-form-errors bag="updatePassword" />

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div class="md:col-span-2 md:max-w-[calc(50%-0.75rem)]">
                    <label for="current_password" class="gf-label">Current password</label>
                    @include('auth.partials.password-input', ['id' => 'current_password', 'autocomplete' => 'current-password'])
                </div>

                <div>
                    <label for="password" class="gf-label">New password</label>
                    @include('auth.partials.password-input', ['id' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8])
                </div>

                <div>
                    <label for="password_confirmation" class="gf-label">Confirm new password</label>
                    @include('auth.partials.password-input', ['id' => 'password_confirmation', 'autocomplete' => 'new-password', 'minlength' => 8])
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4">
            <x-button type="submit" icon="check">Update password</x-button>
        </div>
    </x-card>
</form>

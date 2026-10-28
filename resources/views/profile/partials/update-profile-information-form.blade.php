{{-- Validation errors in the default bag are shown by the layout's SweetAlert. --}}
<form method="POST" action="{{ route('profile.update') }}" data-processing="Saving your profile…">
    @csrf
    @method('PATCH')

    <x-card title="Profile information" icon="user-circle" subtitle="Update your name and email address.">
        <div class="grid grid-cols-1 gap-6 p-6 md:grid-cols-2">
            <div>
                <label for="name" class="gf-label">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name"
                       class="gf-input">
            </div>

            <div>
                <label for="email" class="gf-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="username"
                       class="gf-input">
            </div>

            <div>
                <span class="gf-label">Role</span>
                <p class="gf-readonly-field">{{ $user->isAdmin() ? 'Administrator' : 'User' }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4">
            <x-button type="submit" icon="check">Save profile</x-button>
        </div>
    </x-card>
</form>

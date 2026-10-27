<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            Edit User
        </h2>
    </x-slot>

    <div class="p-6 max-w-lg">
        @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('users.update', $user->id) }}">
            @csrf
            @method('PUT')

            <div class="mb-2">
                <label>Name</label>
                <input name="name" value="{{ $user->name }}" class="border p-2 w-full">
            </div>

            <div class="mb-2">
                <label>Email</label>
                <input name="email" value="{{ $user->email }}" class="border p-2 w-full">
            </div>

            <div class="mb-2">
                <label>Role</label>
                <select name="role" class="border p-2 w-full">
                    <option value="user" {{ $user->role == 'user' ? 'selected' : '' }}>User</option>
                    <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>

            <div class="mb-2">
                <label>New Password (optional)</label>
                <input type="password" name="password" class="border p-2 w-full">
            </div>

            <div class="mb-4">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" class="border p-2 w-full">
            </div>

            <button class="bg-blue-500 text-white px-4 py-2 rounded">
                Update User
            </button>
        </form>
    </div>
</x-app-layout>

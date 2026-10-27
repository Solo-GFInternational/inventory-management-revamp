<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Create User</h2>
    </x-slot>

    <div class="p-6">

    {{-- Success message --}}
    @if(session('success'))
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
        <form method="POST" action="{{ route('users.store') }}">
            @csrf

            <input name="name" placeholder="Name" class="border p-2 w-full mb-2">
            <input name="email" placeholder="Email" class="border p-2 w-full mb-2">
            <input type="password" name="password" placeholder="Password" class="border p-2 w-full mb-2">
            <input type="password" name="password_confirmation" placeholder="Confirm Password" class="border p-2 w-full mb-2">

            <select name="role" class="border p-2 w-full mb-2">
                <option value="user">User</option>
            </select>

            <button class="text-blue-500 hover:underline px-2 py-1 border rounded">
                Create User
            </button>
        </form>
    </div>
</x-app-layout>

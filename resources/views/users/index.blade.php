<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800">
            User List
        </h2>
    </x-slot>

    <div class="p-6">
        <a href="{{ route('users.create') }}"
           class="bg-blue-500 text-white px-4 py-2 rounded">
            Create New User
        </a>

        <div class="overflow-x-auto mt-4">
            <table class="min-w-full bg-white border">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-2 px-4 border-b">Name</th>
                        <th class="py-2 px-4 border-b">Email</th>
                        <th class="py-2 px-4 border-b">Role</th>
                        <th class="py-2 px-4 border-b">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr class="text-center hover:bg-gray-50">
                            <td class="py-2 px-4 border-b">{{ $user->name }}</td>
                            <td class="py-2 px-4 border-b">{{ $user->email }}</td>
                            <td class="py-2 px-4 border-b">{{ ucfirst($user->role) }}</td>
                            <td class="py-2 px-4 border-b">
                                {{ $user->created_at->format('d M Y') }}
                            </td>
                            <td class="py-2 px-4 border-b">
    <a href="{{ route('users.edit', $user->id) }}"
       class="text-blue-500 hover:underline">
        Edit
    </a>

    <form action="{{ route('users.destroy', $user->id) }}"
          method="POST"
          onsubmit="return confirm('Are you sure?');">
        @csrf
        @method('DELETE')

        <button class="text-red-600 hover:underline">
            Delete
        </button>
    </form>
</td>


                        </tr>
                    @endforeach

                    @if($users->count() == 0)
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">
                                No users found
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>

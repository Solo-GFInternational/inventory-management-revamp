@extends('layouts.app')
@section('title', 'Users')

@section('content')
    <x-settings-tabs active="users">
        <x-button icon="user-plus" data-modal-open="create-user">New user</x-button>
    </x-settings-tabs>

    <x-card>
        {{-- Table toolbar: search and the active/inactive filter, above the table. --}}
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-center justify-end gap-3 px-6 py-4">
            <div class="relative w-full sm:w-72">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email" aria-label="Search"
                       class="gf-input ps-10">
            </div>
            <select name="status" aria-label="Status" class="gf-input select-arrow w-full sm:w-48">
                <option value="active" @selected($status === 'active')>Active users</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive users</option>
                <option value="all" @selected($status === 'all')>All users</option>
            </select>
            <x-button type="submit" color="gray" icon="funnel">Filter</x-button>
        </form>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php $isSelf = $user->is(auth()->user()); @endphp
                        <tr>
                            <td class="font-medium">
                                {{ $user->name }}
                                @if ($isSelf)
                                    <span class="ms-1 text-xs font-normal text-gray-400">(you)</span>
                                @endif
                            </td>
                            <td class="text-gray-500">{{ $user->email }}</td>
                            <td>
                                @if ($user->isAdmin())
                                    <x-badge classes="bg-primary-50 text-primary-700 ring-primary-600/20">Admin</x-badge>
                                @else
                                    <x-badge>User</x-badge>
                                @endif
                            </td>
                            <td>
                                @if ($user->isActive())
                                    <x-badge classes="bg-emerald-50 text-emerald-700 ring-emerald-600/20">Active</x-badge>
                                @else
                                    <x-badge classes="bg-gray-100 text-gray-600 ring-gray-500/20">Inactive</x-badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-gray-500">{{ $user->created_at?->format('d M Y') }}</td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button icon="pencil-square" label="Edit {{ $user->name }}"
                                                   data-modal-open="edit-user-{{ $user->id }}" />
                                    @if (! $user->isActive())
                                        <form method="POST" action="{{ route('users.reactivate', $user) }}"
                                              data-confirm="{{ $user->name }} will be able to sign in again with their existing password."
                                              data-confirm-title="Reactivate user?" data-confirm-button="Yes, reactivate"
                                              data-processing="Reactivating…">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button type="submit" icon="arrow-path" label="Reactivate {{ $user->name }}" />
                                        </form>
                                    @elseif ($isSelf)
                                        {{-- Shown disabled with the reason on hover, rather than hidden. --}}
                                        <x-icon-button icon="no-symbol" label="Deactivate {{ $user->name }}" color="danger"
                                                       disabled-reason="You cannot deactivate your own account." />
                                    @else
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              data-confirm="{{ $user->name }} will no longer be able to sign in. Their records are kept, and you can reactivate them later."
                                              data-confirm-title="Deactivate user?" data-confirm-button="Yes, deactivate"
                                              data-processing="Deactivating…">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button type="submit" icon="no-symbol" label="Deactivate {{ $user->name }}" color="danger" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="users" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">No users found</p>
                                @if ($search !== '' || $status !== 'active')
                                    <p class="mt-1 text-sm text-gray-500">Try another search or status filter.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{--
        Create and edit modals. Each form carries a hidden _modal naming its
        dialog, so when it fails validation and redirects back here, that same
        dialog reopens with the input and errors.
    --}}
    @php $reopen = old('_modal'); @endphp

    <x-modal id="create-user" heading="New user" description="A temporary password is created and emailed to them with their sign-in details."
             icon="user-plus" :open="$reopen === 'create-user'">
        @if ($reopen === 'create-user')
            <x-form-errors />
        @endif
        <form id="create-user-form" method="POST" action="{{ route('users.store') }}"
              data-processing="Creating the account…" data-processing-text="Emailing their sign-in details — this can take a moment." class="gf-modal-fields">
            @csrf
            <input type="hidden" name="_modal" value="create-user">
            @include('settings.users._form', ['targetUser' => null, 'prefix' => 'create-user-', 'useOld' => $reopen === 'create-user'])
        </form>

        <x-slot:footer>
            <x-button type="submit" form="create-user-form">Create</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>

    @foreach ($users as $user)
        @php $modalId = 'edit-user-'.$user->id; @endphp
        <x-modal :id="$modalId" heading="Edit user" :description="$user->name.' ('.$user->email.')'" icon="pencil-square"
                 :open="$reopen === $modalId">
            @if ($reopen === $modalId)
                <x-form-errors />
            @endif
            <form id="{{ $modalId }}-form" method="POST" action="{{ route('users.update', $user) }}"
                  data-processing="Saving changes…" class="gf-modal-fields">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="{{ $modalId }}">
                @include('settings.users._form', ['targetUser' => $user, 'prefix' => $modalId.'-', 'useOld' => $reopen === $modalId])
            </form>

            <x-slot:footer>
                <x-button type="submit" form="{{ $modalId }}-form">Save changes</x-button>
                <x-button color="gray" data-modal-close>Cancel</x-button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endsection

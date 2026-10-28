@extends('layouts.app')
@section('title', 'Types')

@section('content')
    <x-settings-tabs active="types">
        <x-button icon="plus" data-modal-open="create-type">New type</x-button>
    </x-settings-tabs>

    <x-card>
        {{-- Table toolbar: search, group and the active/inactive filter, above the table. --}}
        <form method="GET" action="{{ route('types.index') }}" class="flex flex-wrap items-center justify-end gap-3 px-6 py-4">
            <div class="relative w-full sm:w-72">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search name" aria-label="Search"
                       class="gf-input ps-10">
            </div>
            <select name="group" aria-label="Group" class="gf-input select-arrow w-full sm:w-48">
                <option value="">All groups</option>
                @foreach ($groups as $groupValue => $groupLabel)
                    <option value="{{ $groupValue }}" @selected($group === $groupValue)>{{ $groupLabel }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Status" class="gf-input select-arrow w-full sm:w-48">
                <option value="active" @selected($status === 'active')>Active types</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive types</option>
                <option value="all" @selected($status === 'all')>All types</option>
            </select>
            <x-button type="submit" color="gray" icon="funnel">Filter</x-button>
        </form>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Name</th>
                        <th class="text-end">Order</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $type)
                        @php
                            $groupLabel = $groups[$type->type_group] ?? $type->type_group;
                            $uses = $usage[$type->getKey()] ?? 0;
                        @endphp
                        <tr>
                            <td class="text-gray-500">{{ $groupLabel }}</td>
                            <td class="font-medium">{{ $type->type_name }}</td>
                            <td class="text-end text-gray-500 tabular-nums">{{ $type->sort_order }}</td>
                            <td>
                                @if ($type->active_yn)
                                    <x-badge classes="bg-emerald-50 text-emerald-700 ring-emerald-600/20">Active</x-badge>
                                @else
                                    <x-badge classes="bg-gray-100 text-gray-600 ring-gray-500/20">Inactive</x-badge>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button icon="pencil-square" label="Edit {{ $type->type_name }}"
                                                   data-modal-open="edit-type-{{ $type->getKey() }}" />
                                    @if ($type->active_yn)
                                        <form method="POST" action="{{ route('types.destroy', $type) }}"
                                              data-confirm="{{ $type->type_name }} will no longer be offered in the {{ $groupLabel }} list for new records.{{ $uses > 0 ? ' '.$uses.' active inventory '.\Illuminate\Support\Str::plural('record', $uses).' already '.($uses === 1 ? 'uses' : 'use').' it and will keep it.' : ' Records that already use it keep it.' }} You can reactivate it later."
                                              data-confirm-title="Deactivate type?" data-confirm-button="Yes, deactivate"
                                              data-processing="Deactivating…">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button type="submit" icon="no-symbol" label="Deactivate {{ $type->type_name }}" color="danger" />
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('types.reactivate', $type) }}"
                                              data-confirm="{{ $type->type_name }} will be offered in the {{ $groupLabel }} list again."
                                              data-confirm-title="Reactivate type?" data-confirm-button="Yes, reactivate"
                                              data-processing="Reactivating…">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button type="submit" icon="arrow-path" label="Reactivate {{ $type->type_name }}" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="tag" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">No types found</p>
                                @if ($search !== '' || $group !== '' || $status !== 'active')
                                    <p class="mt-1 text-sm text-gray-500">Try another search, group or status filter.</p>
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

    <x-modal id="create-type" heading="New type" description="Add an option to the department or site dropdown."
             icon="tag" :open="$reopen === 'create-type'">
        @if ($reopen === 'create-type')
            <x-form-errors />
        @endif
        <form id="create-type-form" method="POST" action="{{ route('types.store') }}" data-processing="Saving the type…">
            @csrf
            <input type="hidden" name="_modal" value="create-type">
            @include('settings.types._form', ['type' => null, 'prefix' => 'create-type-', 'useOld' => $reopen === 'create-type', 'defaultGroup' => $group ?: null])
        </form>

        <x-slot:footer>
            <x-button type="submit" form="create-type-form">Create</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>

    @foreach ($types as $type)
        @php $modalId = 'edit-type-'.$type->getKey(); @endphp
        <x-modal :id="$modalId" heading="Edit type" :description="($groups[$type->type_group] ?? $type->type_group).' – '.$type->type_name" icon="pencil-square"
                 :open="$reopen === $modalId">
            @if ($reopen === $modalId)
                <x-form-errors />
            @endif
            <form id="{{ $modalId }}-form" method="POST" action="{{ route('types.update', $type) }}" data-processing="Saving changes…">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="{{ $modalId }}">
                @include('settings.types._form', ['type' => $type, 'prefix' => $modalId.'-', 'useOld' => $reopen === $modalId])
            </form>

            <x-slot:footer>
                <x-button type="submit" form="{{ $modalId }}-form">Save changes</x-button>
                <x-button color="gray" data-modal-close>Cancel</x-button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endsection

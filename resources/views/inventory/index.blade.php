@extends('layouts.app')
@section('title', 'Inventory')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $reopen = old('_modal');
        $hasFilters = $filters['taken_by'] !== '' || $filters['site'] !== '' || $filters['status'] !== '';
    @endphp

    <x-page-header title="Inventory"
                   description="Equipment issued to staff. New items come out of stock once the record is approved.">
        {{-- Yearly report: a plain GET form, so it works without JavaScript. --}}
        <form method="GET" action="{{ route('inventory.download.pdf') }}" class="flex items-center gap-2">
            <select name="year" aria-label="Report year" class="gf-input select-arrow w-28">
                @for ($y = now()->year; $y >= 2020; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
            <x-button type="submit" color="gray" icon="arrow-down-tray" class="whitespace-nowrap">Download PDF</x-button>
        </form>
        <x-button icon="plus" data-modal-open="create-inventory">Add inventory</x-button>
    </x-page-header>

    <x-card>
        {{-- Table toolbar: filters above the table, at the right. --}}
        <form method="GET" class="flex flex-wrap items-center justify-end gap-3 px-6 py-4">
            <div class="relative w-full sm:w-72">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" />
                <input type="search" name="taken_by" value="{{ $filters['taken_by'] }}" placeholder="Search taken by" aria-label="Search taken by"
                       class="gf-input ps-10">
            </div>
            <select name="site" aria-label="Site" class="gf-input select-arrow w-full sm:w-56">
                <option value="">All sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site }}" @selected($filters['site'] === $site)>{{ $site }}</option>
                @endforeach
            </select>
            <select name="status" aria-label="Status" class="gf-input select-arrow w-full sm:w-48">
                <option value="">All statuses</option>
                <option value="approved" @selected($filters['status'] === 'approved')>Approved</option>
                <option value="pending" @selected($filters['status'] === 'pending')>Awaiting approval</option>
            </select>
            <x-button type="submit" color="gray" icon="funnel">Filter</x-button>
            @if ($hasFilters)
                <x-button color="gray" :href="route('inventory.index')" icon="x-mark">Clear</x-button>
            @endif
        </form>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>Serial</th>
                        <th>Condition</th>
                        <th>Taken by</th>
                        <th>Department</th>
                        <th>Site</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        @php
                            // Fields changed by an edit awaiting approval are highlighted.
                            $changed = fn (string $field) => $inventory->approval_type === 'edit' && isset(($inventory->change_log ?? [])[$field]);
                        @endphp
                        <tr>
                            <td @class(['whitespace-nowrap', 'font-semibold text-amber-700' => $changed('date')])>
                                {{ \Illuminate\Support\Carbon::parse($inventory->date)->format('d M Y') }}
                            </td>
                            <td @class(['font-medium', 'text-amber-700' => $changed('item')])>{{ $inventory->item }}</td>
                            <td @class(['text-end tabular-nums', 'font-semibold text-amber-700' => $changed('qty')])>{{ $inventory->qty }}</td>
                            <td @class(['text-gray-500', 'font-semibold text-amber-700' => $changed('serial_number')])>{{ $inventory->serial_number ?: '—' }}</td>
                            <td @class(['font-semibold text-amber-700' => $changed('condition')])>{{ ucfirst($inventory->condition) }}</td>
                            <td @class(['font-semibold text-amber-700' => $changed('taken_by')])>
                                {{ $inventory->taken_by }}
                                @if ($inventory->giver)
                                    <span class="block text-xs text-gray-400">Given by {{ $inventory->giver->name }}</span>
                                @endif
                            </td>
                            <td @class(['font-semibold text-amber-700' => $changed('department')])>{{ $inventory->department }}</td>
                            <td @class(['font-semibold text-amber-700' => $changed('site')])>{{ $inventory->site ?: '—' }}</td>
                            <td class="whitespace-nowrap">@include('inventory._status', ['inventory' => $inventory])</td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <x-icon-button icon="pencil-square" label="Edit" data-modal-open="edit-inventory-{{ $inventory->getKey() }}" />
                                    @if ($isAdmin && $inventory->isPending())
                                        <form method="POST" action="{{ route('inventory.approve', $inventory) }}"
                                              data-confirm="{{ $inventory->condition === 'new' ? $inventory->qty.' × '.$inventory->item.' will be taken out of stock.' : 'The record will be marked approved.' }}"
                                              data-confirm-title="Approve this record?" data-confirm-button="Yes, approve">
                                            @csrf
                                            @method('PATCH')
                                            <x-icon-button type="submit" icon="check" label="Approve" />
                                        </form>
                                    @endif
                                    @if ($isAdmin)
                                        <form method="POST" action="{{ route('inventory.destroy', $inventory) }}"
                                              data-confirm="{{ $inventory->status === 'approved' && $inventory->condition === 'new' ? 'The record is hidden and its '.$inventory->qty.' × '.$inventory->item.' go back into stock.' : 'The record is hidden from the inventory list.' }}"
                                              data-confirm-title="Deactivate this record?" data-confirm-button="Yes, deactivate">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button type="submit" icon="trash" label="Deactivate" color="danger" />
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="x-mark" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">No inventory records found</p>
                                @if ($hasFilters)
                                    <p class="mt-1 text-sm text-gray-500">Try clearing the filters.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($inventories->hasPages())
            <div class="border-t border-gray-200 px-6 py-3">{{ $inventories->links() }}</div>
        @endif
    </x-card>

    {{--
        Create and edit modals. Each form carries a hidden _modal naming its
        dialog, so a failed validation reopens that same dialog with its input.
    --}}
    <x-modal id="create-inventory" heading="Add inventory" width="max-w-3xl" icon="plus"
             :description="$isAdmin ? 'New items are taken out of stock straight away.' : 'Your record goes to an administrator for approval; stock is taken when it is approved.'"
             :open="$reopen === 'create-inventory'">
        @if ($reopen === 'create-inventory')
            <x-form-errors />
        @endif
        <form id="create-inventory-form" method="POST" action="{{ route('inventory.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="create-inventory">
            @include('inventory._form', ['inventory' => null, 'prefix' => 'create-inventory-', 'useOld' => $reopen === 'create-inventory'])
        </form>

        <x-slot:footer>
            <x-button type="submit" form="create-inventory-form">{{ $isAdmin ? 'Add' : 'Submit for approval' }}</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>

    @foreach ($inventories as $inventory)
        @php $modalId = 'edit-inventory-'.$inventory->getKey(); @endphp
        <x-modal :id="$modalId" heading="Edit inventory" width="max-w-3xl" icon="pencil-square"
                 :description="$isAdmin ? $inventory->item.' — '.$inventory->taken_by : 'Your changes go to an administrator for approval.'"
                 :open="$reopen === $modalId">
            @if ($reopen === $modalId)
                <x-form-errors />
            @endif
            <form id="{{ $modalId }}-form" method="POST" action="{{ route('inventory.update', $inventory) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="{{ $modalId }}">
                @include('inventory._form', ['inventory' => $inventory, 'prefix' => $modalId.'-', 'useOld' => $reopen === $modalId])
            </form>

            <x-slot:footer>
                <x-button type="submit" form="{{ $modalId }}-form">{{ $isAdmin ? 'Save changes' : 'Submit for approval' }}</x-button>
                <x-button color="gray" data-modal-close>Cancel</x-button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endsection

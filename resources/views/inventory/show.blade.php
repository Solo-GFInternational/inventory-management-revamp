@extends('layouts.app')
@section('title', 'Inventory '.($inventory->barcode ?? '#'.$inventory->getKey()))

{{--
    One inventory record, read-only: where a scanned barcode leads (the
    Scanner page redirects here). Deactivated records are still shown, marked
    as such, so an old label never leads to a dead end.
--}}
@section('content')
    <x-page-header :title="$inventory->stock?->item ?? 'Inventory record'"
                   :description="$inventory->isReturned() ? 'Returned to stock; last held by '.$inventory->taken_by.'.' : 'Issued to '.$inventory->taken_by.'.'">
        <x-slot:before>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                @include('inventory._status', ['inventory' => $inventory])
                @unless ($inventory->active_yn)
                    <x-badge classes="bg-danger-50 text-danger-700 ring-danger-600/20">Deactivated</x-badge>
                @endunless
            </div>
        </x-slot:before>

        {{-- Return to stock / Assign to new person buttons (and their modals). --}}
        @include('inventory._handover')

        @if ($inventory->barcode)
            {{-- A download never leaves the page, so no data-processing spinner. --}}
            <x-button icon="arrow-down-tray" :href="route('inventory.barcode', $inventory)">Download barcode</x-button>
        @endif
        <x-button color="gray" icon="arrow-left" :href="route('inventory.index')">Back to inventory</x-button>
    </x-page-header>

    @unless ($inventory->active_yn)
        <div class="mb-6 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/20">
            This record has been deactivated. It no longer appears in the inventory list or reports.
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Barcode" icon="qr-code">
            <div class="p-6 text-center">
                @if ($inventory->barcode)
                    <img src="{{ route('inventory.barcode', ['inventory' => $inventory, 'inline' => 1]) }}"
                         alt="Barcode {{ $inventory->barcode }}" class="mx-auto w-full max-w-sm rounded-lg ring-1 ring-gray-950/10">
                    <p class="mt-3 font-mono text-base font-semibold tracking-wider text-gray-950">{{ $inventory->barcode }}</p>
                @else
                    <p class="text-sm text-gray-500">No barcode has been assigned yet.</p>
                @endif
            </div>
        </x-card>

        <x-card title="Details" icon="clipboard-document-list" class="lg:col-span-2">
            @php
                // While returned, the holder fields describe who last had it.
                $details = [
                    'Date' => \Illuminate\Support\Carbon::parse($inventory->date)->format('d M Y'),
                    'Item' => $inventory->stock?->item ?? '—',
                    'Condition' => ucfirst($inventory->condition),
                    'Serial number' => $inventory->serial_number ?: '—',
                    ($inventory->isReturned() ? 'Last held by' : 'Taken by') => $inventory->taken_by,
                    'Department' => $inventory->department?->type_name ?? '—',
                    'Site' => $inventory->site?->type_name ?? '—',
                    'Given by' => $inventory->giver?->name ?? '—',
                ];
                if ($inventory->isReturned()) {
                    $details['Returned on'] = $inventory->returned_at->format('d M Y').' by '.($inventory->returner?->name ?? 'unknown');
                }
            @endphp
            <dl class="grid gap-6 p-6 sm:grid-cols-2">
                @foreach ($details as $label => $detail)
                    <div>
                        <dt class="gf-label">{{ $label }}</dt>
                        <dd class="gf-readonly-field">{{ $detail }}</dd>
                    </div>
                @endforeach
                <div>
                    <dt class="gf-label">Status</dt>
                    <dd>@include('inventory._status', ['inventory' => $inventory])</dd>
                </div>
            </dl>
        </x-card>
    </div>

    {{-- The item's timeline: notes and returned / assigned events. --}}
    @include('inventory._timeline')
@endsection

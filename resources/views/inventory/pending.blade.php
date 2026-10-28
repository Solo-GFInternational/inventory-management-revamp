@extends('layouts.app')
@section('alerts', 'tallstackui') {{-- TallStackUI dialogs and toasts instead of SweetAlert (trial) --}}
@section('title', 'Approvals')

@section('content')
    @php
        $fieldLabels = [
            'date' => 'Date', 'qty' => 'Qty', 'item' => 'Item', 'serial_number' => 'Serial',
            'condition' => 'Condition', 'taken_by' => 'Taken by', 'department' => 'Department', 'site' => 'Site',
        ];
    @endphp

    <x-page-header title="Approvals"
                   description="Inventory records added or edited by staff. Approving a new-condition record takes it out of stock.">
        <x-button color="gray" :href="route('inventory.index')" icon="clipboard-document-check">Inventory list</x-button>
    </x-page-header>

    <x-card title="Awaiting approval" icon="clock"
            :subtitle="$inventories->count().' '.\Illuminate\Support\Str::plural('record', $inventories->count())">
        <div class="overflow-x-auto">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th>Condition</th>
                        <th>Taken by</th>
                        <th>Site</th>
                        <th>Submitted by</th>
                        <th>Changes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inventories as $inventory)
                        @php $changes = $inventory->approval_type === 'edit' ? ($inventory->change_log ?? []) : []; @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap">@include('inventory._status', ['inventory' => $inventory])</td>
                            <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($inventory->date)->format('d M Y') }}</td>
                            <td class="font-medium">{{ $inventory->stock?->item }}</td>
                            <td class="text-end tabular-nums">{{ $inventory->qty }}</td>
                            <td>{{ ucfirst($inventory->condition) }}</td>
                            <td>
                                {{ $inventory->taken_by }}
                                <span class="block text-xs text-gray-400">{{ $inventory->department?->type_name }}</span>
                            </td>
                            <td>{{ $inventory->site?->type_name ?? '—' }}</td>
                            <td class="text-gray-500">
                                {{ ($inventory->approval_type === 'edit' ? $inventory->editor : $inventory->giver)?->name ?? 'Unknown' }}
                            </td>
                            <td>
                                @if ($changes)
                                    <dl class="space-y-1 text-xs">
                                        @foreach ($changes as $field => $change)
                                            <div class="flex flex-wrap items-center gap-1">
                                                <dt class="font-semibold text-gray-700">{{ $fieldLabels[$field] ?? $field }}:</dt>
                                                <dd class="flex flex-wrap items-center gap-1">
                                                    <span class="text-gray-500 line-through">{{ ($change['old'] ?? null) ?? '—' }}</span>
                                                    <x-icon name="arrow-right" class="size-3 text-gray-400" />
                                                    <span class="font-medium text-gray-950">{{ ($change['new'] ?? null) ?? '—' }}</span>
                                                </dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                @elseif ($inventory->approval_type === 'edit')
                                    <span class="text-xs text-gray-400">No field changes recorded</span>
                                @else
                                    <span class="text-xs text-gray-400">New record</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('inventory.approve', $inventory) }}"
                                          data-confirm="{{ $inventory->condition === 'new' ? $inventory->qty.' × '.$inventory->stock?->item.' will be taken out of stock.' : 'The record will be marked approved.' }}"
                                          data-confirm-title="Approve this record?" data-confirm-button="Yes, approve"
                                          data-processing="Approving…" data-processing-text="Updating the record and the stock count.">
                                        @csrf
                                        <x-icon-button type="submit" icon="check" label="Approve" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="check" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">Nothing awaiting approval</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection

@extends('layouts.app')
@section('title', 'Stock')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
        $reopen = old('_modal');
    @endphp

    <x-page-header title="Stock" description="In stock is what's on the shelf now; given out is what has been issued as new. Total is both together.">
        <x-button icon="plus" data-modal-open="add-stock">Add stock</x-button>
    </x-page-header>

    {{-- Stats overview: totals across every active item. --}}
    <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
        @foreach ([
            ['In stock', $totals['inStock'], 'archive-box', 'text-primary-500'],
            ['Given out', $totals['givenOut'], 'truck', 'text-amber-500'],
            ['Total', $totals['total'], 'calculator', 'text-gray-400'],
        ] as [$label, $value, $icon, $tone])
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-center gap-x-2">
                    <x-icon :name="$icon" class="size-5 {{ $tone }}" />
                    <span class="text-sm font-medium text-gray-500">{{ $label }}</span>
                </div>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <x-card>
        <form method="GET" class="flex flex-wrap items-center justify-end gap-3 px-6 py-4">
            <div class="relative w-full sm:w-72">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-gray-400" />
                <input type="search" name="q" value="{{ $search }}" placeholder="Search items" aria-label="Search items"
                       class="gf-input ps-10">
            </div>
            <x-button type="submit" color="gray" icon="funnel">Search</x-button>
        </form>

        <div class="overflow-x-auto border-t border-gray-200">
            <table class="gf-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">In stock</th>
                        <th class="text-end">Given out</th>
                        <th class="text-end">Total</th>
                        @if ($isAdmin)
                            <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        <tr>
                            <td class="font-medium">{{ $stock->item }}</td>
                            <td class="text-end tabular-nums whitespace-nowrap">
                                @if ($stock->isOutOfStock())
                                    <x-badge classes="bg-danger-50 text-danger-700 ring-danger-600/20" title="Out of stock">{{ $stock->inStock() }} · Out</x-badge>
                                @elseif ($stock->isLowStock())
                                    <x-badge classes="bg-amber-50 text-amber-700 ring-amber-600/20" title="Low stock">{{ $stock->inStock() }} · Low</x-badge>
                                @else
                                    {{ $stock->inStock() }}
                                @endif
                            </td>
                            <td class="text-end tabular-nums text-gray-500">{{ $stock->givenOut() }}</td>
                            <td class="text-end tabular-nums font-semibold">{{ $stock->total() }}</td>
                            @if ($isAdmin)
                                <td>
                                    <div class="flex items-center justify-end">
                                        <x-icon-button icon="adjustments-horizontal" label="Adjust {{ $stock->item }}"
                                                       data-modal-open="adjust-stock-{{ $stock->id }}" />
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 5 : 4 }}" class="px-6 py-12 text-center">
                                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full bg-gray-100">
                                    <x-icon name="archive-box" class="size-6 text-gray-500" />
                                </div>
                                <p class="text-base font-semibold text-gray-950">
                                    {{ $search === '' ? 'No stock yet' : 'No items match "'.$search.'"' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Add stock: pick an existing item or type a new one. --}}
    <x-modal id="add-stock" heading="Add stock" description="Adds to the in-stock count. A new item name starts a new stock line."
             icon="archive-box-arrow-down" :open="$reopen === 'add-stock'">
        @if ($reopen === 'add-stock')
            <x-form-errors />
        @endif
        <form id="add-stock-form" method="POST" action="{{ route('stock.store') }}" class="space-y-5"
              data-processing="Adding stock…">
            @csrf
            <input type="hidden" name="_modal" value="add-stock">

            <div>
                <label for="add-stock-item" class="gf-label">Existing item</label>
                <select id="add-stock-item" name="item" class="gf-input select-arrow">
                    <option value="">Choose an item…</option>
                    @foreach ($itemNames as $name)
                        <option value="{{ $name }}" @selected($reopen === 'add-stock' && old('item') === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="add-stock-new-item" class="gf-label">Or a new item</label>
                <input id="add-stock-new-item" type="text" name="new_item" maxlength="255" class="gf-input"
                       value="{{ $reopen === 'add-stock' ? old('new_item') : '' }}" placeholder="e.g. USB-C dock">
                <p class="gf-hint">Leave the list above empty when typing a new name.</p>
            </div>

            <div>
                <label for="add-stock-qty" class="gf-label">Quantity to add</label>
                <input id="add-stock-qty" type="number" name="qty" min="1" step="1" required class="gf-input"
                       value="{{ $reopen === 'add-stock' ? old('qty') : '' }}">
            </div>
        </form>

        <x-slot:footer>
            <x-button type="submit" form="add-stock-form">Add stock</x-button>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>

    {{-- Adjust (admin): correct the in-stock count after a recount. --}}
    @if ($isAdmin)
        @foreach ($stocks as $stock)
            @php $modalId = 'adjust-stock-'.$stock->id; @endphp
            <x-modal :id="$modalId" heading="Adjust stock" :description="$stock->item.' — currently '.$stock->inStock().' in stock.'"
                     icon="adjustments-horizontal" :open="$reopen === $modalId">
                @if ($reopen === $modalId)
                    <x-form-errors />
                @endif
                <form id="{{ $modalId }}-form" method="POST" action="{{ route('stock.update', $stock) }}" class="space-y-5"
                      data-processing="Saving the stock count…">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal" value="{{ $modalId }}">

                    <div>
                        <label for="{{ $modalId }}-qty" class="gf-label">Correct in-stock count</label>
                        <input id="{{ $modalId }}-qty" type="number" name="qty" min="0" step="1" required class="gf-input"
                               value="{{ $reopen === $modalId ? old('qty') : $stock->inStock() }}">
                        <p class="gf-hint">Sets the count to this number; it doesn't add to it.</p>
                    </div>

                    <div>
                        <label for="{{ $modalId }}-note" class="gf-label">Reason <span class="font-normal text-gray-400">(optional)</span></label>
                        <input id="{{ $modalId }}-note" type="text" name="note" maxlength="255" class="gf-input"
                               value="{{ $reopen === $modalId ? old('note') : '' }}" placeholder="e.g. Recount after audit">
                    </div>
                </form>

                <x-slot:footer>
                    <x-button type="submit" form="{{ $modalId }}-form">Save count</x-button>
                    <x-button color="gray" data-modal-close>Cancel</x-button>
                </x-slot:footer>
            </x-modal>
        @endforeach
    @endif
@endsection

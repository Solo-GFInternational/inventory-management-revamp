@props(['active', 'description' => null])

{{--
    The "Inventory and Stock" page: one heading over two tabs, laid out like
    x-settings-tabs. Each tab is its own route (inventory.index, stock.index),
    so filters, redirects and links keep working as before. Header actions
    for the current tab (e.g. "Add stock") go in the slot.
--}}
@php
    $tabs = [
        'inventory' => ['Inventory', 'clipboard-document-check', route('inventory.index')],
        'stock' => ['Stock', 'archive-box', route('stock.index')],
    ];
@endphp

<x-page-header title="Inventory and Stock" :description="$description" class="mb-6">
    {{ $slot }}
</x-page-header>

{{-- Small, left-aligned tabs, all as wide as the longest (same style as x-settings-tabs). --}}
<nav aria-label="Inventory and Stock" class="mb-6 inline-grid auto-cols-fr grid-flow-col gap-x-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-950/5">
    @foreach ($tabs as $key => [$label, $icon, $href])
        <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
           @class([
               'flex items-center justify-center gap-x-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition outline-none',
               'bg-primary-50 text-primary-700' => $active === $key,
               'text-gray-500 hover:bg-gray-50 hover:text-gray-700 focus-visible:bg-gray-50' => $active !== $key,
           ])>
            <x-icon :name="$icon" @class(['size-4', 'text-primary-600' => $active === $key, 'text-gray-400' => $active !== $key]) />
            {{ $label }}
        </a>
    @endforeach
</nav>

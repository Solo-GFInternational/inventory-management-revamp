@props(['active'])

{{--
    The Settings area's page header and tab bar: the heading "Settings",
    then small left-aligned tabs, each with its icon, all as wide as the
    longest (auto-cols-fr on an inline grid). Header actions for the current tab
    (e.g. "New user") go in the slot.
--}}
@php
    $tabs = [
        'users' => ['Users', 'users', route('users.index')],
        'audit' => ['Audit Trail', 'clipboard-document-list', route('audit.index')],
        'types' => ['Types', 'tag', route('types.index')],
    ];
@endphp

<x-page-header title="Settings" class="mb-6">
    {{ $slot }}
</x-page-header>

<nav aria-label="Settings" class="mb-6 inline-grid auto-cols-fr grid-flow-col gap-x-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-950/5">
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

@props(['active'])

{{--
    The Settings area's page header and tab bar, as on the site-visit and
    tender projects: the heading "Settings", then the tabs in a card,
    stretched to equal widths, each with its icon. Header actions for the
    current tab (e.g. "New user") go in the slot.
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

<nav aria-label="Settings" class="mb-6 flex gap-x-1 overflow-x-auto rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5">
    @foreach ($tabs as $key => [$label, $icon, $href])
        <a href="{{ $href }}" @if ($active === $key) aria-current="page" @endif
           @class([
               'flex min-h-14 min-w-0 flex-1 items-center justify-center gap-x-2 rounded-lg px-3 py-2 text-base font-medium whitespace-nowrap transition outline-none',
               'bg-gray-50 text-primary-600' => $active === $key,
               'text-gray-500 hover:bg-gray-50 hover:text-gray-700 focus-visible:bg-gray-50' => $active !== $key,
           ])>
            <x-icon :name="$icon" @class(['size-5', 'text-primary-600' => $active === $key, 'text-gray-400' => $active !== $key]) />
            {{ $label }}
        </a>
    @endforeach
</nav>

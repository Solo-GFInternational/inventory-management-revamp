@props(['title', 'description' => null])

{{--
    Filament's page header, as on every site-visit page: a large bold title
    (and optional description) on the left, header actions on the right —
    pass them as the slot, e.g. <x-button href="..." icon="plus">New tender</x-button>.
    An optional `before` slot sits above the title (a back link, a badge).
--}}
<header {{ $attributes->class(['mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        {{ $before ?? '' }}
        <h1 class="text-2xl font-bold tracking-tight text-gray-950 sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-3xl text-sm text-gray-500">{{ $description }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</header>

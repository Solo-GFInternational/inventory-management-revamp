@props(['title' => null, 'subtitle' => null, 'icon' => null])

{{--
    Filament's section — the card every block of a page sits in on the
    site-visit project: rounded-xl, white, ringed and shadowed, with an
    optional heading (plus icon and description) and an `actions` slot at the
    opposite end of the heading row. The body is unpadded so tables can run
    edge to edge; give padded content its own p-6.
--}}
<section {{ $attributes->class(['rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5']) }}>
    @if ($title)
        <header class="flex items-center gap-3 border-b border-gray-200 px-6 py-4">
            @if ($icon)
                <x-icon :name="$icon" class="size-6 text-gray-400" />
            @endif
            <div class="min-w-0 flex-1">
                <h2 class="text-base leading-6 font-semibold text-gray-950">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-sm text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    {{ $slot }}
</section>

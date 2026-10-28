@props(['active' => false, 'icon' => null])

{{--
    A top bar navigation item as on the site-visit project: icon and label,
    roomy padding, white on navy; the current page gets a light tint. Labels
    drop away on narrow screens, leaving the icons. An optional `badge` slot
    sits after the label (the Unmatched count).
--}}
<a @if ($active) aria-current="page" @endif {{ $attributes->class([
    'flex items-center gap-x-2 rounded-lg px-3 py-3 text-white transition outline-none sm:px-5 hover:bg-white/8 focus-visible:bg-white/8',
    'bg-white/12' => $active,
]) }}>
    @if ($icon)
        <x-icon :name="$icon" @class(['size-6', 'text-white' => $active, 'text-white/60' => ! $active]) />
    @endif
    <span class="sr-only text-base font-medium sm:not-sr-only">{{ $slot }}</span>
    {{ $badge ?? '' }}
</a>

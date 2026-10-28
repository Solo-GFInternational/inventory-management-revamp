@props(['color' => 'primary', 'icon' => null, 'href' => null, 'size' => 'md'])

{{--
    Filament's button, as the site-visit project's panel draws it: rounded-lg,
    semibold, optional leading Heroicon. Renders an <a> when given an href,
    otherwise a <button> (type defaults to "button" — pass type="submit").
    color: primary (teal) | gray (white, outlined) | danger (red).
--}}
@php
    $classes = [
        'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold shadow-sm transition outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-70',
        'px-3 py-2 text-sm' => $size === 'md',
        'px-2.5 py-1.5 text-sm' => $size === 'sm',
        'px-4 py-2.5 text-sm' => $size === 'lg',
        'bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500/50' => $color === 'primary',
        'bg-white text-gray-950 ring-1 ring-gray-950/10 hover:bg-gray-50 focus-visible:ring-primary-600' => $color === 'gray',
        'bg-danger-600 text-white hover:bg-danger-500 focus-visible:ring-danger-500/50' => $color === 'danger',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="size-5 {{ $color === 'gray' ? 'text-gray-400' : '' }}" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        @if ($icon)<x-icon :name="$icon" class="size-5 {{ $color === 'gray' ? 'text-gray-400' : '' }}" />@endif
        {{ $slot }}
    </button>
@endif

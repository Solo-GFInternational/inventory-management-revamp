@props(['icon', 'label', 'color' => 'primary', 'href' => null, 'disabledReason' => null])

{{--
    An icon-only row action with its label as the tooltip — the site-visit
    project's .gf-row-btn (solid, a little wider than square, white icon).
    With disabledReason it's drawn faded and inert, the reason as tooltip,
    the way Filament shows an action the policy refuses.
--}}
@php
    $classes = [
        'inline-flex h-9 min-w-13 items-center justify-center rounded-lg px-4 text-white shadow-sm transition outline-none focus-visible:ring-2',
        'bg-primary-600 hover:bg-primary-500 focus-visible:ring-primary-500/50' => $color === 'primary',
        'bg-danger-600 hover:bg-danger-500 focus-visible:ring-danger-500/50' => $color === 'danger',
        'bg-gray-500 hover:bg-gray-400 focus-visible:ring-gray-500/50' => $color === 'gray',
    ];
@endphp

@if ($disabledReason)
    <span title="{{ $disabledReason }}" aria-label="{{ $label }} (unavailable: {{ $disabledReason }})" role="img"
          {{ $attributes->class([...$classes, 'cursor-not-allowed opacity-50']) }}>
        <x-icon :name="$icon" />
    </span>
@elseif ($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" />
    </a>
@else
    <button title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['type' => 'button'])->class($classes) }}>
        <x-icon :name="$icon" />
    </button>
@endif

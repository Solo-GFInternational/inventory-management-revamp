@props(['name'])

{{--
    A Heroicons outline icon (the set the site-visit project's Filament panel
    uses), read from resources/svg/heroicons/{name}.svg — e.g.
    <x-icon name="pencil-square" class="size-5" />. Sized by the caller;
    defaults to size-5, Filament's button icon size.
--}}
@php
    $inner = preg_replace('/^.*?<svg[^>]*>|<\/svg>\s*$/s', '', file_get_contents(resource_path("svg/heroicons/{$name}.svg")));
@endphp

<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
     aria-hidden="true" data-slot="icon" {{ $attributes->class(['shrink-0', 'size-5' => ! str_contains($attributes->get('class', ''), 'size-')]) }}>{!! $inner !!}</svg>

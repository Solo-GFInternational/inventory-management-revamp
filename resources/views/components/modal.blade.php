@props(['id', 'heading', 'description' => null, 'icon' => null, 'width' => 'max-w-2xl', 'open' => false])

{{--
    Filament's action modal (the create/edit dialogs on site-visit's Staff
    Accounts and Sites), built on a native <dialog> so no JS framework is
    needed — resources/js/app.js wires it up:
      - any element with data-modal-open="{id}" opens it (and an <a> with
        that attribute keeps its href as the no-JS fallback);
      - anything inside with data-modal-close closes it, as do Esc and a
        click on the backdrop;
      - :open="true" opens it as soon as the page loads — pass it when the
        form inside failed validation, so the errors show where they were made.
    Put the form fields in the slot and the buttons in the `footer` slot; to
    submit a form from the footer, give the button form="{form id}".
--}}
<dialog id="{{ $id }}" @if ($open) data-open-on-load @endif aria-labelledby="{{ $id }}-heading"
        {{ $attributes->class(["m-auto w-[calc(100%-2rem)] {$width} rounded-xl bg-white p-0 text-gray-950 shadow-xl ring-1 ring-gray-950/5 backdrop:bg-gray-950/50"]) }}>
    <div class="flex items-start gap-4 px-6 pt-6">
        @if ($icon)
            <div class="rounded-full bg-primary-100 p-2 text-primary-600">
                <x-icon :name="$icon" class="size-6" />
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <h2 id="{{ $id }}-heading" class="text-base leading-6 font-semibold text-gray-950">{{ $heading }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            @endif
        </div>
        <button type="button" data-modal-close title="Close" aria-label="Close"
                class="-m-1.5 rounded-lg p-1.5 text-gray-400 hover:bg-gray-50 hover:text-gray-500">
            <x-icon name="x-mark" class="size-6" />
        </button>
    </div>

    <div class="px-6 py-6">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex flex-wrap items-center gap-3 px-6 pb-6">
            {{ $footer }}
        </div>
    @endisset
</dialog>

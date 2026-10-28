{{--
    A password input with the show/hide toggle (resources/js/app.js).
    Pass: id (also the name), autocomplete; optional minlength, autofocus.
--}}
<div class="relative" data-password-field>
    <input id="{{ $id }}" name="{{ $id }}" type="password" required autocomplete="{{ $autocomplete }}"
           @if (! empty($minlength)) minlength="{{ $minlength }}" @endif
           @if (! empty($autofocus)) autofocus @endif
           class="gf-input pe-10">
    <button type="button" data-password-toggle aria-label="Show password"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-500">
        <span data-icon-show><x-icon name="eye" /></span>
        <span data-icon-hide hidden><x-icon name="eye-slash" /></span>
    </button>
</div>

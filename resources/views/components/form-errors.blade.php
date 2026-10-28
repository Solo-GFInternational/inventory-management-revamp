@props(['bag' => 'default'])

{{--
    Validation errors at the top of a form, for forms inside a modal
    (<x-modal>). A modal that reopens on a failed submit shows its errors
    here; on a plain page they're shown by the layout's SweetAlert instead.
--}}
@if ($errors->getBag($bag)->any())
    <div {{ $attributes->class(['mb-5 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 ring-1 ring-danger-600/20']) }}>
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->getBag($bag)->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

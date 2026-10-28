@extends('layouts.app')
@section('title', 'Barcode scanner')

@push('scripts')
    @vite('resources/js/scanner.js')
@endpush

@section('content')
    <x-page-header title="Barcode scanner" description="Scan an inventory label to open its record." />

    <div class="grid gap-6">
        <x-card title="Scan with the camera" icon="camera">
            <div class="flex flex-col items-center gap-4 px-6 py-10 text-center">
                <x-icon name="viewfinder-circle" class="size-16 text-primary-500" />
                <p class="max-w-md text-sm text-gray-500">
                    Hold the item's barcode label up to the camera, flat and well lit, with the whole barcode inside the box.
                    The record opens as soon as the label is read.
                </p>
                <x-button size="lg" icon="camera" data-modal-open="scanner-modal" data-scanner-open>Scan barcode</x-button>
            </div>
        </x-card>

        {{-- For a label the camera can't read, or a computer without a camera. --}}
        <x-card title="Enter the barcode instead" icon="pencil-square"
                subtitle="Type the code printed under the barcode, e.g. INV000123.">
            <form method="GET" action="{{ route('scanner.lookup') }}" class="flex flex-wrap items-end gap-3 px-6 py-6">
                <div class="w-full sm:w-72">
                    <label for="scanner-code" class="gf-label">Barcode</label>
                    <input id="scanner-code" type="text" name="code" value="{{ old('code') }}" required maxlength="32"
                           autocomplete="off" spellcheck="false" placeholder="INV000123" class="gf-input uppercase">
                </div>
                <x-button type="submit" icon="magnifying-glass">Find record</x-button>
            </form>
        </x-card>
    </div>

    {{-- The live camera view. resources/js/scanner.js starts the camera when this opens and stops it when it closes. --}}
    <x-modal id="scanner-modal" heading="Scan barcode" icon="camera"
             description="Line the barcode up inside the box." data-lookup-url="{{ route('scanner.lookup') }}">
        <div class="gf-modal-fields">
            <div class="relative aspect-video overflow-hidden rounded-lg bg-gray-950">
                <video data-scanner-video class="size-full object-cover" playsinline muted></video>

                {{-- Guide box, wide and short to suit a 1D barcode, with a scan line across the middle. --}}
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                    <div class="relative h-1/3 w-4/5 rounded-lg border-2 border-white/90 shadow-[0_0_0_9999px_rgb(3_7_18/0.45)]">
                        <div class="absolute inset-x-3 top-1/2 h-0.5 -translate-y-1/2 bg-danger-500/80"></div>
                    </div>
                </div>

                <p data-scanner-status class="absolute inset-x-0 bottom-0 bg-gray-950/60 px-3 py-1.5 text-center text-xs text-white">
                    Starting the camera…
                </p>
            </div>

            {{-- Shown by scanner.js only when there's more than one camera. --}}
            <div data-scanner-camera-field class="mt-4" hidden>
                <label for="scanner-camera" class="gf-label">Camera</label>
                <select id="scanner-camera" data-scanner-camera class="gf-input select-arrow"></select>
            </div>
        </div>

        <x-slot:footer>
            <x-button color="gray" data-modal-close>Cancel</x-button>
        </x-slot:footer>
    </x-modal>
@endsection

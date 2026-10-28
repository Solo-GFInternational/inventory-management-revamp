<?php

use App\Models\Inventory;
use App\Services\BarcodeLabel;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// One-off backfill: records created before barcodes existed (or whose label
// file has gone) get their barcode value and JPEG. Safe to re-run.
Artisan::command('inventory:barcodes', function (BarcodeLabel $labels) {
    $count = 0;

    Inventory::query()->orderBy('id')->each(function (Inventory $inventory) use ($labels, &$count) {
        $hadOne = $inventory->barcode !== null;

        $inventory->assignBarcode();
        $labels->ensure($inventory);

        $count += $hadOne ? 0 : 1;
    });

    $this->info("Barcodes assigned to {$count} record(s); missing label files regenerated.");
})->purpose('Assign missing inventory barcodes and regenerate missing label JPEGs');

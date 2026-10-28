<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each inventory record gets its own Code 128 barcode (e.g. INV000123),
 * assigned once the row has an id. The JPEG lives on the local disk at
 * barcodes/{barcode}.jpg. Unique across all rows, deactivated ones included,
 * so a printed label never points at a different record; filtered because
 * the value is filled in just after the insert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory.inventories', function (Blueprint $table) {
            $table->string('barcode', 32)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX [ux_inventories_barcode] ON [inventory].[inventories] ([barcode]) WHERE [barcode] IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX [ux_inventories_barcode] ON [inventory].[inventories]');

        Schema::table('inventory.inventories', function (Blueprint $table) {
            $table->dropColumn('barcode');
        });
    }
};

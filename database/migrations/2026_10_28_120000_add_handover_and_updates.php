<?php

use App\Support\Database\StandardColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Handover and the per-item timeline.
 *
 * An inventory record is the physical item (its barcode label stays on it).
 * Returning it to stock stamps returned_at / returned_by; assigning it to a
 * new person clears them again. inventory.inventory_updates is the item's
 * timeline: free-text notes people add (e.g. "new battery fitted") and the
 * returned / assigned events the system writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory.inventories', function (Blueprint $table) {
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()
                ->constrained('security.users', 'id', 'fk_inventories_users_returned_by')->noActionOnDelete();
        });

        Schema::create('inventory.inventory_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')
                ->constrained('inventory.inventories', 'id', 'fk_inventory_updates_inventories')->noActionOnDelete();
            $table->string('kind', 20);
            $table->text('body')->nullable();
            StandardColumns::add($table, 'inventory_updates');

            $table->index(['inventory_id', 'created_at'], 'ix_inventory_updates_inventory_id_created_at');
        });

        StandardColumns::checkIn('[inventory].[inventory_updates]', 'ck_inventory_updates_kind', 'kind', ['note', 'returned', 'assigned']);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory.inventory_updates');

        Schema::table('inventory.inventories', function (Blueprint $table) {
            $table->dropForeign('fk_inventories_users_returned_by');
            $table->dropColumn(['returned_at', 'returned_by']);
        });
    }
};

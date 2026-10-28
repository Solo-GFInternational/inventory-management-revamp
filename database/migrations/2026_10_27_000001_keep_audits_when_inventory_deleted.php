<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The cascading foreign key deleted every audit row for an inventory
     * record (including the "deleted" audit itself) the moment it was deleted.
     * Audits are a historical log, so keep inventory_id as a plain indexed
     * column that survives the inventory row.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['inventory_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->foreign('inventory_id')->references('id')->on('inventories')->onDelete('cascade');
        });
    }
};

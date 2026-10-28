<?php

use App\Support\Database\StandardColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per stock item: its name and how many are on the shelf now.
        // Inventory rows point here, so an item is named in exactly one place.
        Schema::create('inventory.stocks', function (Blueprint $table) {
            $table->id();
            $table->string('item');
            $table->integer('qty')->default(0);

            StandardColumns::add($table, 'stocks');
        });

        StandardColumns::uniqueWhereActive('[inventory].[stocks]', 'ux_stocks_item', ['item']);
        DB::statement('ALTER TABLE [inventory].[stocks] ADD CONSTRAINT [ck_stocks_qty] CHECK ([qty] >= 0)');

        // Equipment issued to a person.
        Schema::create('inventory.inventories', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('stock_id');
            $table->integer('qty');
            $table->string('serial_number')->nullable();
            $table->string('condition', 10);
            $table->string('taken_by');
            $table->foreignId('department_id');
            $table->foreignId('site_id');
            $table->foreignId('given_by');
            $table->string('status', 10)->default('approved');
            $table->string('approval_type', 10)->nullable();
            $table->text('change_log')->nullable();
            $table->foreignId('edited_by')->nullable();

            StandardColumns::add($table, 'inventories');

            $table->foreign('stock_id', 'fk_inventories_stocks')->references('id')->on('inventory.stocks')->noActionOnDelete();
            $table->foreign('department_id', 'fk_inventories_types_department_id')->references('id')->on('ref.types')->noActionOnDelete();
            $table->foreign('site_id', 'fk_inventories_types_site_id')->references('id')->on('ref.types')->noActionOnDelete();
            $table->foreign('given_by', 'fk_inventories_users_given_by')->references('id')->on('security.users')->noActionOnDelete();
            $table->foreign('edited_by', 'fk_inventories_users_edited_by')->references('id')->on('security.users')->noActionOnDelete();

            $table->index('stock_id', 'ix_inventories_stock_id');
            $table->index('department_id', 'ix_inventories_department_id');
            $table->index('site_id', 'ix_inventories_site_id');
            $table->index(['status', 'active_yn'], 'ix_inventories_status_active_yn');
            $table->index('date', 'ix_inventories_date');
        });

        StandardColumns::checkIn('[inventory].[inventories]', 'ck_inventories_condition', 'condition', ['new', 'used']);
        StandardColumns::checkIn('[inventory].[inventories]', 'ck_inventories_status', 'status', ['approved', 'pending']);
        StandardColumns::checkIn('[inventory].[inventories]', 'ck_inventories_approval_type', 'approval_type', ['new', 'edit'], nullable: true);
        DB::statement('ALTER TABLE [inventory].[inventories] ADD CONSTRAINT [ck_inventories_qty] CHECK ([qty] >= 1)');

        // Fixed assets (not yet used by the app; carried over from the old schema).
        Schema::create('inventory.assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->nullable();
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->string('status', 20)->default('available');
            $table->text('disposal_reason')->nullable();
            $table->timestamp('disposed_at')->nullable();

            StandardColumns::add($table, 'assets');
        });

        StandardColumns::uniqueWhereActive('[inventory].[assets]', 'ux_assets_asset_tag', ['asset_tag']);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory.assets');
        Schema::dropIfExists('inventory.inventories');
        Schema::dropIfExists('inventory.stocks');
    }
};

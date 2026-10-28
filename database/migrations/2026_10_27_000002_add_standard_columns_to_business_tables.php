<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business tables that get active_yn / created_by / updated_by.
     * audits is an append-only log and keeps its own user_id + timestamps.
     */
    private array $tables = ['users', 'inventories', 'stocks', 'items', 'assets'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('active_yn')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('no action');
                $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('no action');
            });
        }

        // items.active predates the standard; carry its values over and drop it.
        DB::table('items')->update(['active_yn' => DB::raw('active')]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('active')->default(true);
        });

        DB::table('items')->update(['active' => DB::raw('active_yn')]);

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropColumn(['active_yn', 'created_by', 'updated_by']);
            });
        }
    }
};

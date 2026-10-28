<?php

use App\Support\Database\StandardColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic lookup table behind the app's dropdowns (modelled on the
     * tender-management project's ref.types): one row per option, grouped by
     * type_group ('department', 'site', ...). Only lists people manage belong
     * here; values that drive behaviour (role, condition, status) stay as
     * checked columns on their own tables.
     */
    public function up(): void
    {
        Schema::create('ref.types', function (Blueprint $table) {
            $table->id();
            $table->string('type_group', 50);
            $table->string('type_name', 100);
            $table->integer('sort_order')->default(0);

            StandardColumns::add($table, 'types');

            $table->index(['type_group', 'sort_order'], 'ix_types_type_group_sort_order');
        });

        StandardColumns::uniqueWhereActive('[ref].[types]', 'ux_types_type_group_type_name', ['type_group', 'type_name']);
    }

    public function down(): void
    {
        Schema::dropIfExists('ref.types');
    }
};

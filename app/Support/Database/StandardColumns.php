<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Migration helpers for the house database conventions: the standard
 * business-table columns, and constraints named ux_/ix_/fk_/ck_<table>_<cols>.
 * SQL Server only — production and local dev both run on SQL Server.
 */
class StandardColumns
{
    /**
     * active_yn, created_by, created_at, updated_by, updated_at, with the two
     * user columns as foreign keys to security.users (NO ACTION — SQL Server
     * rejects multiple cascade paths).
     *
     * $name is the bare table name, used in constraint names.
     */
    public static function add(Blueprint $table, string $name): void
    {
        $table->boolean('active_yn')->default(true);
        $table->foreignId('created_by')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->foreignId('updated_by')->nullable();
        $table->timestamp('updated_at')->nullable();

        $table->foreign('created_by', "fk_{$name}_users_created_by")->references('id')->on('security.users')->noActionOnDelete();
        $table->foreign('updated_by', "fk_{$name}_users_updated_by")->references('id')->on('security.users')->noActionOnDelete();
    }

    /**
     * A unique index that only covers active rows, so a deactivated row
     * doesn't block reusing its value.
     */
    public static function uniqueWhereActive(string $qualifiedTable, string $name, array $columns): void
    {
        $columnList = implode(', ', array_map(fn ($column) => "[{$column}]", $columns));

        DB::statement("CREATE UNIQUE INDEX [{$name}] ON {$qualifiedTable} ({$columnList}) WHERE [active_yn] = 1");
    }

    /**
     * A named CHECK constraint limiting a column to a fixed list of values.
     */
    public static function checkIn(string $qualifiedTable, string $name, string $column, array $values, bool $nullable = false): void
    {
        $list = implode(', ', array_map(fn ($value) => "N'".str_replace("'", "''", $value)."'", $values));
        $condition = "[{$column}] IN ({$list})".($nullable ? " OR [{$column}] IS NULL" : '');

        DB::statement("ALTER TABLE {$qualifiedTable} ADD CONSTRAINT [{$name}] CHECK ({$condition})");
    }
}

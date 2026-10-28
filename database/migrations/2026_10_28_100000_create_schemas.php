<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables are grouped into schemas by concern:
     *   security   accounts and sign-in (users, password reset tokens)
     *   inventory  the business data (stocks, inventories, assets)
     *   ref        reference / lookup data (types)
     *   logs       append-only logs (activity_log, the audit trail)
     * Framework plumbing (migrations, cache, jobs, sessions) stays in dbo.
     */
    private array $schemas = ['security', 'inventory', 'ref', 'logs'];

    public function up(): void
    {
        foreach ($this->schemas as $schema) {
            DB::statement("IF SCHEMA_ID(N'{$schema}') IS NULL EXEC(N'CREATE SCHEMA [{$schema}]')");
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->schemas) as $schema) {
            DB::statement("IF SCHEMA_ID(N'{$schema}') IS NOT NULL EXEC(N'DROP SCHEMA [{$schema}]')");
        }
    }
};

<?php

use App\Support\Database\StandardColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security.users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 20)->default('user');
            $table->rememberToken();

            StandardColumns::add($table, 'users');
        });

        StandardColumns::uniqueWhereActive('[security].[users]', 'ux_users_email', ['email']);
        StandardColumns::checkIn('[security].[users]', 'ck_users_role', 'role', ['admin', 'user']);

        // Framework plumbing: no standard columns.
        Schema::create('security.password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary('pk_password_reset_tokens');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security.password_reset_tokens');
        Schema::dropIfExists('security.users');
    }
};

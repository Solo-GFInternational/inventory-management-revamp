<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail (spatie/laravel-activitylog; table name set in
     * config/activitylog.php). Append-only, so no standard columns: causer_id
     * says who and created_at says when. Every causer is a user, so causer_id
     * is a real foreign key; subjects span several tables (polymorphic), so
     * subject_id can't be.
     */
    public function up(): void
    {
        Schema::create('logs.activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->foreign('causer_id', 'fk_activity_log_users_causer_id')->references('id')->on('security.users')->noActionOnDelete();

            $table->index('log_name', 'ix_activity_log_log_name');
            $table->index(['subject_type', 'subject_id'], 'ix_activity_log_subject');
            $table->index(['causer_type', 'causer_id'], 'ix_activity_log_causer');
            $table->index('created_at', 'ix_activity_log_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs.activity_log');
    }
};

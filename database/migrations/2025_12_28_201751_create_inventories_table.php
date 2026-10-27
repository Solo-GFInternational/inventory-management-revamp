<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
{
    Schema::create('inventories', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->integer('qty');
        $table->string('item');
        $table->string('serial_number')->nullable();
        $table->enum('condition', ['new', 'used']);
        $table->foreignId('given_by')->constrained('users');
        $table->string('taken_by');
        $table->string('department');
        $table->enum('status', ['approved', 'pending'])->default('approved');
        $table->foreignId('edited_by')->nullable()->constrained('users');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventories');
    }
};

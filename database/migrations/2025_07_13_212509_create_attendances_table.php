<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('attendances', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('employee_id'); // ✅ مهم
    $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');

    $table->date('date');
    $table->time('check_in')->nullable();
    $table->time('check_out')->nullable();
    $table->time('late_by')->nullable();
    $table->time('left_early_by')->nullable();
    $table->time('worked_hours')->nullable();

    $table->timestamps();
    $table->unique(['employee_id', 'date']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

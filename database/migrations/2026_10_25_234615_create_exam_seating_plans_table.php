<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_seating_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->uuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('roll_number')->nullable();
            $table->string('seat_number')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_seating_plans');
    }
};

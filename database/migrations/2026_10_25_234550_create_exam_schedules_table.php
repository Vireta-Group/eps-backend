<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('exam_calendar_id')->constrained('exam_calendars')->cascadeOnDelete();
            $table->uuid('exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->uuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->string('start_time');
            $table->string('end_time');
            $table->decimal('total_marks', 5, 2);
            $table->uuid('room_id')->nullable();
            $table->uuid('invigilator_id')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};

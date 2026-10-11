<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendance_finalizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->integer('total_days')->default(0);
            $table->integer('working_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('half_day_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'month', 'year'], 'teacher_attendance_finalization_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance_finalizations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leave_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('staff'); // teacher_id or employee_id
            $table->foreignUuid('leave_type_id')->constrained('staff_leave_types')->cascadeOnDelete();
            $table->uuid('academic_year_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 6, 1);
            $table->string('reason', 2000);
            $table->string('status', 20)->default('pending');
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_remarks')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session', 20)->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_applications');
    }
};

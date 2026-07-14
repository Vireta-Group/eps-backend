<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendance_regularizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->nullable()->constrained('teacher_attendances')->nullOnDelete();
            $table->date('attendance_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->default('present');
            $table->text('reason');
            $table->string('supporting_doc_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('requested_by')->nullable();
            $table->timestampsTz();

            $table->index(['teacher_id', 'attendance_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance_regularizations');
    }
};

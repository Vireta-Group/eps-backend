<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_regularizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->nullable()->constrained('student_attendances')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->default('excused');
            $table->text('reason');
            $table->string('supporting_doc_url')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('requested_by')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'attendance_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularizations');
    }
};

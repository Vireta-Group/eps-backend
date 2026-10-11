<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendance_edits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->constrained('teacher_attendances')->cascadeOnDelete();
            $table->foreignUuid('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampTz('created_at');

            $table->index('attendance_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance_edits');
    }
};

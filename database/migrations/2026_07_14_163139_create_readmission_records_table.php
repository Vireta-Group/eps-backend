<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readmission_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('dropout_record_id')->nullable()->constrained('dropout_records')->nullOnDelete();
            $table->date('readmission_date');
            $table->text('reason')->nullable();
            $table->foreignUuid('new_academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignUuid('new_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('new_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->boolean('fee_settled')->default(false);
            $table->text('remarks')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readmission_records');
    }
};

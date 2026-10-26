<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_exam_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('subject_id'); // Referencing subject from academic module
            $table->foreignUuid('exam_type_id')->constrained('exam_types')->cascadeOnDelete();
            $table->foreignUuid('grading_system_id')->nullable()->constrained('grading_systems')->nullOnDelete();
            $table->decimal('pass_marks', 5, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_exam_configs');
    }
};

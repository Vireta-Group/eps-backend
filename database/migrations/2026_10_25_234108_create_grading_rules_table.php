<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grading_system_id')->constrained('grading_systems')->cascadeOnDelete();
            $table->string('grade_name');
            $table->decimal('min_mark', 5, 2);
            $table->decimal('max_mark', 5, 2);
            $table->decimal('gpa_point', 3, 2);
            $table->string('grade_color')->nullable();
            $table->string('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_rules');
    }
};

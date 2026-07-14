<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('condition_name', 200);
            $table->string('condition_type', 50)->default('chronic');
            $table->text('description')->nullable();
            $table->text('management_plan')->nullable();
            $table->string('doctor_name', 200)->nullable();
            $table->string('doctor_contact', 50)->nullable();
            $table->text('medication')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_conditions');
    }
};

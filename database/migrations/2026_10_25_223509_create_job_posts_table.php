<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('job_title');
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('job_type', 50); // Full-time, Part-time, etc.
            $table->integer('vacancies')->default(1);
            $table->text('job_description');
            $table->jsonb('responsibilities')->nullable();
            $table->jsonb('required_qualifications')->nullable();
            $table->jsonb('preferred_qualifications')->nullable();
            $table->jsonb('required_skills')->nullable();
            $table->integer('experience_years')->nullable();
            $table->integer('age_limit')->nullable();
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('location', 255)->nullable();
            $table->date('application_deadline')->nullable();
            $table->date('joining_date')->nullable();
            $table->boolean('is_internal_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posts');
    }
};

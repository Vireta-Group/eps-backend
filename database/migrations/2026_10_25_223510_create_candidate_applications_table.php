<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('job_post_id')->constrained('job_posts')->cascadeOnDelete();
            $table->string('candidate_name');
            $table->string('candidate_email')->unique();
            $table->string('candidate_phone');
            $table->text('address')->nullable();
            $table->jsonb('educational_qualifications')->nullable();
            $table->jsonb('work_experience')->nullable();
            $table->jsonb('skills')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->integer('notice_period_days')->nullable();
            $table->string('cv_url');
            $table->text('cover_letter')->nullable();
            $table->string('status', 30)->default('applied'); // applied, under_review, shortlisted, interview_scheduled, selected, rejected, on_hold
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'job_post_id']);
            $table->index('status');
        });

        Schema::table('onboarding_checklists', function (Blueprint $table) {
            $table->foreign('candidate_application_id')->references('id')->on('candidate_applications')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_applications');
    }
};

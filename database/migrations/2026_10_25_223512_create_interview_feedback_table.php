<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('interview_schedule_id')->constrained('interview_schedules')->cascadeOnDelete();
            $table->foreignUuid('interviewer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('communication_rating')->nullable();
            $table->integer('technical_rating')->nullable();
            $table->integer('problem_solving_rating')->nullable();
            $table->integer('attitude_rating')->nullable();
            $table->integer('overall_rating')->nullable();
            $table->text('detailed_comments')->nullable();
            $table->string('recommendation', 30); // Hire, Hold, Reject
            $table->boolean('is_confidential')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->index(['tenant_id', 'interview_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_feedbacks');
    }
};

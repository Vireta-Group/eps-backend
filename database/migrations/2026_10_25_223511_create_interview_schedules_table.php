<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->integer('round_number')->default(1);
            $//L_..._interview_type' la l'intervue type (In-person, Phone, Video)
            $table->string('interview_type', 50); 
            $table->datetime('scheduled_at');
            $table->string('meeting_link')->nullable();
            $table->integer('duration_minutes')->default(30);
            $table->jsonb('interviewer_ids')->nullable(); // Array of User IDs
            $table->string('status', 20)->default('scheduled'); // scheduled, completed, cancelled, no_show
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'candidate_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_schedules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->string('task_name');
            $table->string('category', 50); // document, account, hardware, orientation
            $table->boolean('is_completed')->default(false);
            $table->timestampTz('completed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('completed_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'candidate_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_checklists');
    }
};

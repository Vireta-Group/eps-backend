<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Training type (Induction, Technical, SoftSkills, Safety, Compliance, Leadership, Custom)
            $table->string('training_type', 50); 
            $table->text('description')->nullable();
            $table->string('trainer_name')->nullable();
            $table->string('trainer_type', 20)->default('internal'); // internal, external
            // Duration in minutes
            $table->integer('duration_minutes')->nullable();
            $table->datetime('scheduled_at');
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->integer('max_participants')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};

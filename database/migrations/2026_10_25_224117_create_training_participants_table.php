<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->morphs('staff'); // Teacher or Employee
            $table->string('status', 20)->default('enrolled'); // enrolled, attended, absent, completed
            $table->timestampTz('attended_at')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('score')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->unique(['training_session_id', 'staff_id', 'staff_type'], 'training_participant_unique');
            $table->index(['tenant_id', 'staff_id', 'staff_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};

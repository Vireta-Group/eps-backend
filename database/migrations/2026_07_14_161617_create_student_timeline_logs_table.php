<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_timeline_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->text('description')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampTz('created_at');

            $table->index('tenant_id');
            $table->index('student_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_timeline_logs');
    }
};

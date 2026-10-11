<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_fingerprint_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('fingerprint_devices')->nullOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('punch_time');
            $table->string('direction', 10)->default('entry');
            $table->text('raw_data')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestampTz('processed_at')->nullable();
            $table->boolean('synced')->default(false);
            $table->timestampTz('synced_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->index(['teacher_id', 'punch_time']);
            $table->index(['device_id', 'synced']);
            $table->index('processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_fingerprint_logs');
    }
};

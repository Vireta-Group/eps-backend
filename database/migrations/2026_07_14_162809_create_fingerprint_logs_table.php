<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('fingerprint_devices')->nullOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('scan_time');
            $table->string('direction', 10)->default('entry');
            $table->text('raw_data')->nullable();
            $table->boolean('synced')->default(false);
            $table->timestampTz('synced_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->index(['student_id', 'scan_time']);
            $table->index(['device_id', 'synced']);
            $table->index('scan_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_logs');
    }
};

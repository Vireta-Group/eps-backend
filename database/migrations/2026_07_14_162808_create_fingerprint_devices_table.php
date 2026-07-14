<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('device_name', 200);
            $table->string('device_model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('location', 200)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->integer('port')->nullable();
            $table->string('shift_type', 20)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_online')->default(false);
            $table->timestampTz('last_sync_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_devices');
    }
};

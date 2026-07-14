<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tc_verify_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transfer_certificate_id')->constrained('transfer_certificates')->cascadeOnDelete();
            $table->string('tc_number', 50);
            $table->string('verified_by_ip', 45)->nullable();
            $table->foreignUuid('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at');
            $table->boolean('is_valid');
            $table->jsonb('verification_data')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();

            $table->index('transfer_certificate_id');
            $table->index('tc_number');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tc_verify_logs');
    }
};

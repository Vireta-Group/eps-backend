<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behaviour_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('incident_type', 50);
            $table->text('description');
            $table->string('severity', 20)->default('moderate');
            $table->jsonb('involved_students')->nullable();
            $table->jsonb('witnesses')->nullable();
            $table->jsonb('evidence_urls')->nullable();
            $table->foreignUuid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'incident_date']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behaviour_incidents');
    }
};

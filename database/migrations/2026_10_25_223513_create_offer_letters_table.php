<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_letters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->decimal('offered_salary', 12, 2);
            $table->date('joining_date');
            $table->jsonb('benefits')->nullable();
            $table->string('status', 30)->default('sent'); // sent, accepted, rejected, expired
            $table->timestampTz('accepted_at')->nullable();
            $table->string('digital_signature_url')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'candidate_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_letters');
    }
};

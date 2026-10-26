<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->uuidMorphs('staff'); // Teacher or Employee
            // Certificate number (unique per tenant)
            $table->string('certificate_number', 50);
            $table->date('issue_date');
            $table->string('file_url')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'certificate_number']);
            $table->index(['tenant_id', 'staff_id', 'staff_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_certificates');
    }
};

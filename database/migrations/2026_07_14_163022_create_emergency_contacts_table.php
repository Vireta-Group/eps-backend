<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('contact_name', 200);
            $table->string('relationship', 50);
            $table->string('mobile', 20);
            $table->string('alternative_mobile', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('preferred_hospital')->nullable();
            $table->string('doctor_name', 200)->nullable();
            $table->string('doctor_contact', 50)->nullable();
            $table->text('insurance_info')->nullable();
            $table->text('special_instructions')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
    }
};

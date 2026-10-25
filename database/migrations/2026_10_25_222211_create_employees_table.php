<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id', 50)->unique();
            $table->string('designation', 100);
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employment_type', 50); // Permanent, Contractual, Part-time, etc.
            $table->uuid('reporting_manager_id')->nullable();
            $table->string('work_location', 255)->nullable();
            $table->date('joining_date');
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->string('salary_grade', 50)->nullable();
            $table->json('bank_info')->nullable();
            $table->string('tin_number', 50)->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'is_active']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

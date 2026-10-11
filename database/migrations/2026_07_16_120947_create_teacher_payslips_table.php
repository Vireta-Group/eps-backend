<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_salary_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('month');
            $table->integer('year');
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('house_rent', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->json('other_allowances')->nullable();
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->json('deductions')->nullable();
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['teacher_id', 'month', 'year'], 'teacher_payslip_unique');
            $table->index(['month', 'year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_payslips');
    }
};

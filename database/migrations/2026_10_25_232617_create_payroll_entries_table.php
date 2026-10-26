<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_process_id');
            $table->uuid('staff_id'); // Polymorphic-like: can be Teacher or Employee
            $table->string('staff_type'); // 'teacher' or 'employee'
            $table->decimal('basic_salary', 15, 2);
            $table->json('earnings'); // short_code => value
            $table->json('deductions'); // short_code => value
            $table->decimal('gross_salary', 15, 2);
            $table->decimal('taxable_income', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->decimal('total_deductions', 15, 2);
            $table->decimal('net_salary', 15, 2);
            $table->enum('payment_status', ['pending', 'paid', 'partially_paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};

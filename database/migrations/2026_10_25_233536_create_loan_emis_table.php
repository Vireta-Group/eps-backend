<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_emis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_loan_id')->constrained('staff_loans')->cascadeOnDelete();
            $table->integer('emi_number');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2);
            $table->decimal('total_emi', 15, 2);
            $table->date('due_date');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'partially_paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_emis');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('separation_id')->nullable()->constrained('teacher_separations')->nullOnDelete();
            $table->date('last_working_day');
            $table->jsonb('components')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->jsonb('deductions')->nullable();
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('payment_method', 50)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('receipt_no', 50)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_settlements');
    }
};

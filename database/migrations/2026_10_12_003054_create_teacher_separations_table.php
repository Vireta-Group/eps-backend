<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_separations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('termination_type', 30)->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->date('resignation_date')->nullable();
            $table->date('last_working_day');
            $table->text('reason')->nullable();
            $table->integer('notice_period_days')->default(0);
            $table->boolean('notice_served')->default(false);
            $table->boolean('notice_waived')->default(false);
            $table->decimal('notice_penalty_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('receipt_no', 50)->nullable();
            $table->string('letter_reference', 50)->nullable();
            $table->text('review_remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_separations');
    }
};

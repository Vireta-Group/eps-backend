<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropout_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->date('dropout_date');
            $table->string('dropout_category', 50);
            $table->text('reason')->nullable();
            $table->date('last_attendance_date')->nullable();
            $table->text('supporting_note')->nullable();
            $table->decimal('fee_due_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('dropout_category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropout_records');
    }
};

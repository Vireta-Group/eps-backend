<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_increments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('performance_review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->uuid('staff_id');
            $table->string('staff_type');
            $table->decimal('previous_basic', 15, 2);
            $table->decimal('increment_amount', 15, 2);
            $table->decimal('new_basic', 15, 2);
            $table->decimal('percentage_increase', 5, 2);
            $table->date('effective_date');
            $table->timestampsTz();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_increments');
    }
};

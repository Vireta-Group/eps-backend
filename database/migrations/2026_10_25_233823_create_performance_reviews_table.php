<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('staff_id'); // Polymorphic: Teacher or Employee
            $table->string('staff_type'); // 'teacher' or 'employee'
            $table->string('review_period'); // e.g., '2026-Q4' or '2026-Annual'
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('manager_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('rating_band'); // e.g., 'Outstanding', 'Exceeds Expectations', 'Meets'
            $table->text('manager_comments')->nullable();
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'finalized'])->default('draft');
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};

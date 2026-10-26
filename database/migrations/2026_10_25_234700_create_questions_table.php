<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type')->default('mcq');
            $table->decimal('marks', 5, 2)->default(1);
            $table->decimal('negative_marking', 5, 2)->default(0);
            $table->text('answer_guide')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->string('bloom_level')->nullable();
            $table->string('source')->nullable();
            $table->string('language')->default('bn');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletes();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positive_behaviours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('behaviour_type', 50);
            $table->text('description');
            $table->boolean('reward_given')->default(false);
            $table->text('reward_description')->nullable();
            $table->foreignUuid('recognized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('recognition_date');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positive_behaviours');
    }
};

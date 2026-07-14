<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('active');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'student_id']);
            $table->index('user_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_students');
    }
};

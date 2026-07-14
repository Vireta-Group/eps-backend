<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_qualifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('degree', 100);
            $table->string('subject', 200)->nullable();
            $table->string('institution', 200)->nullable();
            $table->string('board', 100)->nullable();
            $table->string('result', 50)->nullable();
            $table->integer('passing_year')->nullable();
            $table->string('certificate_url')->nullable();
            $table->string('status', 20)->default('verified');
            $table->boolean('is_highest')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_qualifications');
    }
};

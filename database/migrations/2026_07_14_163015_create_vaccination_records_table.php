<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaccination_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('vaccine_name', 200);
            $table->string('dose_number', 20)->nullable();
            $table->date('vaccination_date');
            $table->date('next_due_date')->nullable();
            $table->string('healthcare_provider', 200)->nullable();
            $table->text('certificate_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'next_due_date']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccination_records');
    }
};

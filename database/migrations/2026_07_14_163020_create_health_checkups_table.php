<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checkups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->date('checkup_date');
            $table->string('checkup_type', 50);
            $table->string('doctor_name', 200)->nullable();
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('report_url')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'checkup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checkups');
    }
};

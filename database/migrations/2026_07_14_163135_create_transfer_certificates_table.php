<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('tc_number', 50)->unique();
            $table->date('issue_date');
            $table->date('last_attendance_date')->nullable();
            $table->string('conduct', 20)->nullable();
            $table->text('leaving_reason')->nullable();
            $table->string('leaving_reason_category', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('checklist_cleared')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->uuid('original_tc_id')->nullable();
            $table->string('previous_school_name', 200)->nullable();
            $table->text('previous_school_address')->nullable();
            $table->date('received_date')->nullable();
            $table->text('scan_url')->nullable();
            $table->boolean('is_received')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('tc_number');
            $table->index('status');
        });

        Schema::table('transfer_certificates', function (Blueprint $table) {
            $table->foreign('original_tc_id')->references('id')->on('transfer_certificates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_certificates');
    }
};

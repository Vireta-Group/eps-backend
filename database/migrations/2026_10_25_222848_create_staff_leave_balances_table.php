<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('staff'); // teacher_id or employee_id
            $table->uuid('leave_type_id');
            $table->uuid('academic_year_id')->nullable();
            $table->decimal('total_days', 6, 1);
            $table->decimal('used_days', 6, 1)->default(0);
            $table->decimal('remaining_days', 6, 1)->default(0);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_balances');
    }
};

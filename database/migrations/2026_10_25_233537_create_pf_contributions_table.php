<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pf_contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('staff_id'); // Polymorphic: Teacher or Employee
            $table->string('staff_type'); // 'teacher' or 'employee'
            $table->decimal('employee_contribution', 15, 2);
            $table->decimal('employer_contribution', 15, 2);
            $table->decimal('total_contribution', 15, 2);
            $table->string('month'); // YYYY-MM
            $table->string('year');
            $table->timestamp('contribution_date');
            $table->timestampsTz();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pf_contributions');
    }
};

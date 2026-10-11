<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_payslips', function (Blueprint $table) {
            $table->jsonb('attendance_summary')->nullable();
            $table->decimal('attendance_deduction', 12, 2)->default(0);
            $table->decimal('attendance_addition', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_payslips', function (Blueprint $table) {
            $table->dropColumn(['attendance_summary', 'attendance_deduction', 'attendance_addition']);
        });
    }
};

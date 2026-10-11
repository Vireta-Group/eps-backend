<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendance_settings', function (Blueprint $table) {
            $table->string('late_deduction_mode', 20)->default('none');
            $table->decimal('late_deduction_fixed_amount', 12, 2)->default(0);
            $table->integer('late_to_half_day_threshold')->default(3);
            $table->boolean('overtime_enabled')->default(false);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'late_deduction_mode',
                'late_deduction_fixed_amount',
                'late_to_half_day_threshold',
                'overtime_enabled',
                'overtime_hourly_rate',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_leave_applications', function (Blueprint $table) {
            $table->boolean('is_half_day')->default(false);
            $table->string('half_day_session', 20)->nullable();
            $table->boolean('is_emergency')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_leave_applications', function (Blueprint $table) {
            $table->dropColumn(['is_half_day', 'half_day_session', 'is_emergency']);
        });
    }
};

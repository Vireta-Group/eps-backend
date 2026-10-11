<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_leave_balances', function (Blueprint $table) {
            $table->decimal('total_days', 6, 1)->default(0)->change();
            $table->decimal('used_days', 6, 1)->default(0)->change();
            $table->decimal('remaining_days', 6, 1)->default(0)->change();
        });

        Schema::table('teacher_leave_applications', function (Blueprint $table) {
            $table->decimal('total_days', 6, 1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_leave_balances', function (Blueprint $table) {
            $table->integer('total_days')->default(0)->change();
            $table->integer('used_days')->default(0)->change();
            $table->integer('remaining_days')->default(0)->change();
        });

        Schema::table('teacher_leave_applications', function (Blueprint $table) {
            $table->integer('total_days')->change();
        });
    }
};

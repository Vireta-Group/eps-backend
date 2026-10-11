<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_attendance_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('edit_window_minutes')->default(60);
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->time('late_after_time')->nullable();
            $table->decimal('half_day_min_hours', 4, 2)->nullable();
            $table->decimal('overtime_after_hours', 4, 2)->nullable();
            $table->boolean('selfie_required')->default(false);
            $table->boolean('mock_location_detection')->default(true);
            $table->boolean('allow_geofence_override')->default(true);
            $table->unsignedInteger('geofence_radius_meters')->default(100);
            $table->string('default_status', 20)->default('present');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance_settings');
    }
};

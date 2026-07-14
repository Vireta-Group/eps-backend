<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS attendance_enums_and_tables');

        DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'attendance_status') THEN CREATE TYPE attendance_status AS ENUM ('present','absent','late','half_day','on_leave','excused','suspended'); END IF; END $$;");
        DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'incident_severity') THEN CREATE TYPE incident_severity AS ENUM ('minor','moderate','serious','critical'); END IF; END $$;");
        DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'action_type') THEN CREATE TYPE action_type AS ENUM ('verbal_warning','written_warning','parent_meeting','detention','suspension','community_service','expulsion','custom_action'); END IF; END $$;");

        Schema::create('attendance_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period_name', 100);
            $table->string('period_name_bn', 200)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
        });

        Schema::create('student_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present');
            $table->foreignUuid('period_id')->nullable()->constrained('attendance_periods')->nullOnDelete();
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUuid('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['student_id', 'attendance_date', 'period_id'], 'student_attendance_unique');
            $table->index('attendance_date');
            $table->index(['school_section_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('attendance_periods');
        DB::statement('DROP TYPE IF EXISTS attendance_status');
        DB::statement('DROP TYPE IF EXISTS incident_severity');
        DB::statement('DROP TYPE IF EXISTS action_type');
    }
};

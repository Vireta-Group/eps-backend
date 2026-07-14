<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('roll_number')->nullable();
            $table->boolean('is_current')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->string('status', 20)->default('active');
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('academic_year_id');
            $table->index('school_class_id');
            $table->index('school_section_id');
            $table->index('is_current');
            $table->index('status');
        });

        DB::statement('ALTER TABLE student_academic_histories ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE student_academic_histories ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE student_academic_histories ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_histories');
    }
};

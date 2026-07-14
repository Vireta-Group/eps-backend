<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createEnums();

        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->string('student_id', 50)->unique();
            $table->string('name_en', 100);
            $table->string('name_bn', 200)->nullable();
            $table->string('name_ar', 200)->nullable();
            $table->date('date_of_birth');
            $table->string('gender', 20);
            $table->string('blood_group', 10)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('nationality', 50)->default('Bangladeshi');
            $table->string('birth_reg_no', 50)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('current_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->date('admission_date');
            $table->string('student_type', 20)->default('regular');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->string('status', 20)->default('active');
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id');
            $table->index('gender');
            $table->index('admission_date');
            $table->index('is_active');
            $table->index('status');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->nullable()->change();
        });

        DB::statement('ALTER TABLE students ALTER COLUMN gender DROP DEFAULT');
        DB::statement('ALTER TABLE students ALTER COLUMN gender TYPE gender_type USING gender::gender_type');
        DB::statement("ALTER TABLE students ALTER COLUMN gender SET DEFAULT 'male'");

        DB::statement('ALTER TABLE students ALTER COLUMN student_type DROP DEFAULT');
        DB::statement('ALTER TABLE students ALTER COLUMN student_type TYPE student_type USING student_type::student_type');
        DB::statement("ALTER TABLE students ALTER COLUMN student_type SET DEFAULT 'regular'");

        DB::statement('ALTER TABLE students ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE students ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE students ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
        DB::statement('DROP TYPE IF EXISTS gender_type');
        DB::statement('DROP TYPE IF EXISTS student_type');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE gender_type AS ENUM ('male', 'female', 'other');
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE student_type AS ENUM ('regular', 'special', 'scholarship');
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

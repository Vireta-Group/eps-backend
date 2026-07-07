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

        Schema::create('academic_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->string('year_name', 100);
            $table->string('year_name_bn', 200)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->string('year_status', 20)->default('upcoming');
            $table->boolean('is_locked')->default(false);
            $table->timestampTz('locked_at')->nullable();
            $table->uuid('locked_by')->nullable();
            $table->boolean('result_published')->default(false);
            $table->boolean('promotion_completed')->default(false);
            $table->boolean('fee_settled')->default(false);
            $table->boolean('library_cleared')->default(false);
            $table->uuid('copied_from_year_id')->nullable();
            $table->string('copy_scope', 50)->nullable();
            $table->boolean('is_demo')->default(false);
            $table->string('status', 20)->default('active');
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('year_status');
            $table->index('is_current');
            $table->index('status');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->foreign('copied_from_year_id')
                ->references('id')
                ->on('academic_years')
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_academic_years_active_name
            ON academic_years (tenant_id, COALESCE(branch_id, '00000000-0000-0000-0000-000000000000'), year_name)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement('ALTER TABLE academic_years ALTER COLUMN year_status DROP DEFAULT');
        DB::statement('ALTER TABLE academic_years ALTER COLUMN year_status TYPE year_status USING year_status::year_status');
        DB::statement("ALTER TABLE academic_years ALTER COLUMN year_status SET DEFAULT 'upcoming'");

        DB::statement('ALTER TABLE academic_years ALTER COLUMN copy_scope TYPE copy_scope USING copy_scope::copy_scope');

        DB::statement('ALTER TABLE academic_years ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE academic_years ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE academic_years ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
        DB::statement('DROP TYPE IF EXISTS copy_scope');
        DB::statement('DROP TYPE IF EXISTS year_status');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE year_status AS ENUM (
                    'upcoming', 'active', 'transitioning', 'archived'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE copy_scope AS ENUM (
                    'class_section_only',
                    'class_section_subject',
                    'class_section_subject_routine',
                    'all_except_students'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

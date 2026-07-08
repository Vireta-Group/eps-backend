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

        Schema::create('school_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('section_name', 100);
            $table->string('section_name_bn', 200)->nullable();
            $table->integer('max_capacity')->nullable();
            $table->uuid('class_teacher_id')->nullable();
            $table->string('room_number', 50)->nullable();
            $table->string('shift', 20)->default('morning');
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
            $table->index('school_class_id');
            $table->index('shift');
            $table->index('is_active');
            $table->index('status');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_school_sections_active_name
            ON school_sections (tenant_id, school_class_id, section_name)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement('ALTER TABLE school_sections ALTER COLUMN shift DROP DEFAULT');
        DB::statement('ALTER TABLE school_sections ALTER COLUMN shift TYPE shift_type USING shift::shift_type');
        DB::statement("ALTER TABLE school_sections ALTER COLUMN shift SET DEFAULT 'morning'");

        DB::statement('ALTER TABLE school_sections ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE school_sections ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE school_sections ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('school_sections');
        DB::statement('DROP TYPE IF EXISTS shift_type');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE shift_type AS ENUM (
                    'morning', 'day', 'evening'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

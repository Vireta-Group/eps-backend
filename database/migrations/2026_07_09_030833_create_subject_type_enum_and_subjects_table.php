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

        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->string('subject_name', 100);
            $table->string('subject_name_bn', 200)->nullable();
            $table->string('subject_code', 50)->nullable();
            $table->string('subject_type', 20)->default('theory');
            $table->decimal('credit_hour', 4, 2)->nullable();
            $table->boolean('is_optional')->default(false);
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
            $table->index('subject_type');
            $table->index('is_active');
            $table->index('status');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_subjects_active_name
            ON subjects (tenant_id, subject_name)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_subjects_active_code
            ON subjects (tenant_id, subject_code)
            WHERE deleted_at IS NULL AND subject_code IS NOT NULL
        SQL);

        DB::statement('ALTER TABLE subjects ALTER COLUMN subject_type DROP DEFAULT');
        DB::statement('ALTER TABLE subjects ALTER COLUMN subject_type TYPE subject_type USING subject_type::subject_type');
        DB::statement("ALTER TABLE subjects ALTER COLUMN subject_type SET DEFAULT 'theory'");

        DB::statement('ALTER TABLE subjects ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE subjects ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE subjects ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        DB::statement('DROP TYPE IF EXISTS subject_type');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE subject_type AS ENUM (
                    'theory', 'practical', 'both'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

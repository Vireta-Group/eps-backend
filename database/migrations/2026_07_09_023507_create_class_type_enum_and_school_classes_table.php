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

        Schema::create('school_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('branch_id')->nullable();
            $table->string('class_name', 100);
            $table->string('class_name_bn', 200)->nullable();
            $table->integer('numeric_order');
            $table->string('class_type', 20)->default('regular');
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
            $table->index('branch_id');
            $table->index('numeric_order');
            $table->index('is_active');
            $table->index('status');
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignUuid('branch_id')->nullable()->change();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_school_classes_active_name
            ON school_classes (tenant_id, class_name)
            WHERE deleted_at IS NULL
        SQL);

        DB::statement('ALTER TABLE school_classes ALTER COLUMN class_type DROP DEFAULT');
        DB::statement('ALTER TABLE school_classes ALTER COLUMN class_type TYPE class_type USING class_type::class_type');
        DB::statement("ALTER TABLE school_classes ALTER COLUMN class_type SET DEFAULT 'regular'");

        DB::statement('ALTER TABLE school_classes ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE school_classes ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE school_classes ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
        DB::statement('DROP TYPE IF EXISTS class_type');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE class_type AS ENUM (
                    'regular', 'special', 'vocational'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

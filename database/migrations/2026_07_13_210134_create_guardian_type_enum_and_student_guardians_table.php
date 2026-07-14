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

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('guardian_type', 20);
            $table->string('name_en', 100);
            $table->string('name_bn', 200)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('nid_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->string('status', 20)->default('active');
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('guardian_type');
            $table->index('is_primary');
            $table->index('status');
        });

        DB::statement('ALTER TABLE student_guardians ALTER COLUMN guardian_type TYPE guardian_type USING guardian_type::guardian_type');

        DB::statement('ALTER TABLE student_guardians ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE student_guardians ALTER COLUMN status TYPE record_status USING status::record_status');
        DB::statement("ALTER TABLE student_guardians ALTER COLUMN status SET DEFAULT 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        DB::statement('DROP TYPE IF EXISTS guardian_type');
    }

    private function createEnums(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                CREATE TYPE guardian_type AS ENUM (
                    'father', 'mother', 'local_guardian', 'custom'
                );
            EXCEPTION WHEN duplicate_object THEN NULL;
            END $$;
        SQL);
    }
};

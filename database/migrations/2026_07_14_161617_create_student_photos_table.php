<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->text('photo_url');
            $table->boolean('is_current')->default(true);
            $table->timestampTz('archived_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id');
            $table->index('student_id');
            $table->index('is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_photos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_separation_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('separation_id')->constrained('teacher_separations')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('title', 200)->nullable();
            $table->string('file_url', 255)->nullable();
            $table->text('notes')->nullable();
            $table->date('document_date')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'separation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_separation_documents');
    }
};

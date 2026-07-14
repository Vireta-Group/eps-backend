<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('document_name', 200);
            $table->text('file_url');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_demo')->default(false);
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable();
            $table->text('delete_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();

            $table->index('tenant_id');
            $table->index('student_id');
            $table->index('document_type');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};

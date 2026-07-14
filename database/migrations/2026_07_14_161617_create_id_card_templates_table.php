<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('template_name', 100);
            $table->jsonb('front_layout')->nullable();
            $table->jsonb('back_layout')->nullable();
            $table->string('card_width', 20)->default('85.6mm');
            $table->string('card_height', 20)->default('54mm');
            $table->string('font_family', 100)->nullable();
            $table->string('color_theme', 20)->nullable();
            $table->boolean('is_default')->default(false);
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
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_templates');
    }
};

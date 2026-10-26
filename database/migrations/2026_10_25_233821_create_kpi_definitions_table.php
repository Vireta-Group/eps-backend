<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description');
            $table->string('category'); // e.g., Academic, Administrative
            $table->decimal('weight', 5, 2); // Percentage weight for the total score
            $table->string('measurement_unit'); // e.g., Percentage, Count, Score
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('capacity')->default(0);
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->text('facilities')->nullable();
            $table->string('status')->default('available');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rooms');
    }
};

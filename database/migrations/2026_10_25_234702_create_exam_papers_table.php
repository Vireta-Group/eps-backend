<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_schedule_id')->constrained('exam_schedules')->cascadeOnDelete();
            $table->string('title');
            $table->string('set_code')->nullable();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->integer('time_limit_minutes')->default(60);
            $table->boolean('auto_generated')->default(false);
            $table->string('status')->default('draft');
            $table->timestampsTz();
            $table->softDeletes();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_papers');
    }
};
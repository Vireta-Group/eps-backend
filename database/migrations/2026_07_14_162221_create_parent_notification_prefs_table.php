<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_notification_prefs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 50);
            $table->boolean('sms')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('push')->default(true);
            $table->string('status', 20)->default('active');
            $table->boolean('is_demo')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'student_id', 'event_type']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_notification_prefs');
    }
};

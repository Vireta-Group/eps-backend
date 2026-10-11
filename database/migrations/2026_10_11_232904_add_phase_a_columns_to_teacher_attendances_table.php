<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->foreignUuid('leave_application_id')->nullable()->constrained('teacher_leave_applications')->nullOnDelete();
            $table->boolean('is_mock_location')->default(false);
            $table->string('selfie_url')->nullable();
            $table->string('device_info')->nullable();
            $table->decimal('work_hours', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            $table->timestampTz('editable_until')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->smallInteger('edit_count')->default(0);

            $table->index('leave_application_id');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropIndex(['leave_application_id']);
            $table->dropConstrainedForeignId('leave_application_id');
            $table->dropColumn([
                'is_mock_location',
                'selfie_url',
                'device_info',
                'work_hours',
                'overtime_hours',
                'editable_until',
                'locked_at',
                'edit_count',
            ]);
        });
    }
};

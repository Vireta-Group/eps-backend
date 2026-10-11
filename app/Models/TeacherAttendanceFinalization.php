<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendanceFinalization extends Model
{
    use HasUuids;

    protected $table = 'teacher_attendance_finalizations';

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'month',
        'year',
        'total_days',
        'working_days',
        'present_days',
        'absent_days',
        'late_days',
        'half_day_days',
        'leave_days',
        'notes',
        'finalized_by',
        'finalized_at',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
            'total_days' => 'integer',
            'working_days' => 'integer',
            'present_days' => 'integer',
            'absent_days' => 'integer',
            'late_days' => 'integer',
            'half_day_days' => 'integer',
            'leave_days' => 'integer',
            'finalized_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}

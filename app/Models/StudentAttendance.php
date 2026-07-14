<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAttendance extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'student_attendances';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academic_year_id',
        'school_class_id',
        'school_section_id',
        'attendance_date',
        'status',
        'period_id',
        'arrival_time',
        'departure_time',
        'remarks',
        'marked_by',
        'source',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_HALF_DAY = 'half_day';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_EXCUSED = 'excused';

    public const STATUS_SUSPENDED = 'suspended';

    public const SOURCE_MANUAL = 'manual';

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'arrival_time' => 'datetime:H:i',
            'departure_time' => 'datetime:H:i',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AttendancePeriod::class, 'period_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

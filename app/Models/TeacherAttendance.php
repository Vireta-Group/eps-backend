<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherAttendance extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_attendances';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'academic_year_id',
        'leave_application_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'status',
        'latitude',
        'longitude',
        'location_name',
        'is_mock_location',
        'selfie_url',
        'device_info',
        'work_hours',
        'overtime_hours',
        'editable_until',
        'locked_at',
        'edit_count',
        'source',
        'remarks',
        'marked_by',
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

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_OFF_DAY = 'off_day';

    public const STATUS_WORK_FROM_HOME = 'work_from_home';

    public const STATUS_ON_DUTY = 'on_duty';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FINGERPRINT = 'fingerprint';

    public const SOURCE_MOBILE = 'mobile';

    public const SOURCE_LEAVE = 'leave';

    /**
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_HALF_DAY,
        self::STATUS_ON_LEAVE,
        self::STATUS_EXCUSED,
        self::STATUS_HOLIDAY,
        self::STATUS_OFF_DAY,
        self::STATUS_WORK_FROM_HOME,
        self::STATUS_ON_DUTY,
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_mock_location' => 'boolean',
            'work_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'editable_until' => 'datetime',
            'locked_at' => 'datetime',
            'edit_count' => 'integer',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isEditWindowOpen(): bool
    {
        return $this->editable_until !== null && now()->lessThan($this->editable_until);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function leaveApplication(): BelongsTo
    {
        return $this->belongsTo(TeacherLeaveApplication::class, 'leave_application_id');
    }

    public function edits(): HasMany
    {
        return $this->hasMany(TeacherAttendanceEdit::class, 'attendance_id');
    }
}

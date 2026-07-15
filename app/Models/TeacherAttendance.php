<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendance extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_attendances';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'academic_year_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'status',
        'latitude',
        'longitude',
        'location_name',
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

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FINGERPRINT = 'fingerprint';

    public const SOURCE_MOBILE = 'mobile';

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
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
}

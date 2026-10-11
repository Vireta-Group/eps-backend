<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherLeaveApplication extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_leave_applications';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'leave_type_id',
        'academic_year_id',
        'start_date',
        'end_date',
        'total_days',
        'is_half_day',
        'half_day_session',
        'is_emergency',
        'reason',
        'status',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const HALF_DAY_MORNING = 'morning';

    public const HALF_DAY_AFTERNOON = 'afternoon';

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'total_days' => 'decimal:1',
            'is_half_day' => 'boolean',
            'is_emergency' => 'boolean',
            'reviewed_at' => 'datetime',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

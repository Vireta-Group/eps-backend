<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAttendanceRegularization extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_attendance_regularizations';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'attendance_id',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'old_status',
        'new_status',
        'reason',
        'supporting_doc_url',
        'status',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
        'is_demo',
        'requested_by',
    ];

    protected $hidden = [
        'requested_by',
        'is_demo',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_time' => 'datetime:H:i',
            'check_out_time' => 'datetime:H:i',
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

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(TeacherAttendance::class, 'attendance_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

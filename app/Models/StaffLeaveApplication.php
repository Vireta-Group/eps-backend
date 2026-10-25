<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffLeaveApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'staff_id',
        'staff_type',
        'leave_type_id',
        'academic_year_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
        'is_half_day',
        'half_day_session',
        'is_emergency',
        'is_demo',
        'created_by',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'total_days' => 'decimal:1',
            'is_half_day' => 'boolean',
            'is_emergency' => 'boolean',
            'reviewed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff(): MorphTo
    {
        return $this->morphTo();
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(StaffLeaveType::class, 'leave_type_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

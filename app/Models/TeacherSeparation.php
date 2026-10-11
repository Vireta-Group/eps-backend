<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeacherSeparation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_separations';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'type',
        'termination_type',
        'submitted_by',
        'resignation_date',
        'last_working_day',
        'reason',
        'notice_period_days',
        'notice_served',
        'notice_waived',
        'notice_penalty_amount',
        'status',
        'receipt_no',
        'letter_reference',
        'review_remarks',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const TYPE_RESIGNATION = 'resignation';

    public const TYPE_TERMINATION = 'termination';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const TERMINATION_PERFORMANCE = 'performance';

    public const TERMINATION_MISCONDUCT = 'misconduct';

    public const TERMINATION_REDUNDANCY = 'redundancy';

    public const TERMINATION_END_OF_CONTRACT = 'end_of_contract';

    public const TERMINATION_RETIREMENT = 'retirement';

    public const TERMINATION_DEATH = 'death';

    public const TERMINATION_TYPES = [
        self::TERMINATION_PERFORMANCE,
        self::TERMINATION_MISCONDUCT,
        self::TERMINATION_REDUNDANCY,
        self::TERMINATION_END_OF_CONTRACT,
        self::TERMINATION_RETIREMENT,
        self::TERMINATION_DEATH,
    ];

    protected function casts(): array
    {
        return [
            'resignation_date' => 'date:Y-m-d',
            'last_working_day' => 'date:Y-m-d',
            'notice_period_days' => 'integer',
            'notice_served' => 'boolean',
            'notice_waived' => 'boolean',
            'notice_penalty_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'is_demo' => 'boolean',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TeacherSeparationDocument::class, 'separation_id');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(TeacherSettlement::class, 'separation_id');
    }
}

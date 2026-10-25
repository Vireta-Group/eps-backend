<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewFeedback extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'interview_schedule_id',
        'interviewer_id',
        'communication_rating',
        'technical_rating',
        'problem_solving_rating',
        'attitude_rating',
        'overall_rating',
        'detailed_comments',
        'recommendation',
        'is_confidential',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const RECOMMENDATION_HIRE = 'Hire';
    public const RECOMMENDATION_HOLD = 'Hold';
    public const RECOMMENDATION_REJECT = 'Reject';

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function interviewSchedule(): BelongsTo
    {
        return $this->belongsTo(InterviewSchedule::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }
}

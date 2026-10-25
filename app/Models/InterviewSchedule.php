<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'candidate_application_id',
        'round_number',
        'interview_type',
        'scheduled_at',
        'meeting_link',
        'duration_minutes',
        'interviewer_ids',
        'status',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected function casts(): array
    {
        return [
            'interviewer_ids' => 'array',
            'scheduled_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }
}

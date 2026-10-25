<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateApplication extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'job_post_id',
        'candidate_name',
        'candidate_email',
        'candidate_phone',
        'address',
        'educational_qualifications',
        'work_experience',
        'skills',
        'expected_salary',
        'notice_period_days',
        'cv_url',
        'cover_letter',
        'status',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_APPLIED = 'applied';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_SHORTLISTED = 'shortlisted';
    public const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';
    public const STATUS_SELECTED = 'selected';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ON_HOLD = 'on_hold';

    protected function casts(): array
    {
        return [
            'educational_qualifications' => 'array',
            'work_experience' => 'array',
            'skills' => 'array',
            'expected_salary' => 'decimal:2',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(InterviewSchedule::class);
    }

    public function offerLetter(): HasOne
    {
        return $this->hasOne(OfferLetter::class);
    }

    public function onboardingChecklist(): HasMany
    {
        return $this->hasMany(OnboardingChecklist::class);
    }
}

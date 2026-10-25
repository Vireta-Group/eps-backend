<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPost extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'department_id',
        'job_title',
        'job_type',
        'vacancies',
        'job_description',
        'responsibilities',
        'required_qualifications',
        'preferred_qualifications',
        'required_skills',
        'experience_years',
        'age_limit',
        'salary_min',
        'salary_max',
        'location',
        'application_deadline',
        'joining_date',
        'is_internal_only',
        'is_active',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'responsibilities' => 'array',
            'required_qualifications' => 'array',
            'preferred_qualifications' => 'array',
            'required_skills' => 'array',
            'application_deadline' => 'date',
            'joining_date' => 'date',
            'is_internal_only' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }
}

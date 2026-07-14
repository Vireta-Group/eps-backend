<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'student_id';
    }

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'name_en',
        'name_bn',
        'name_ar',
        'father_name',
        'mother_name',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'nationality',
        'birth_reg_no',
        'mobile',
        'email',
        'current_address',
        'permanent_address',
        'photo_url',
        'admission_date',
        'student_type',
        'is_active',
        'is_demo',
        'status',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected $appends = [
        'roll_number',
    ];

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    public const GENDER_OTHER = 'other';

    public const STUDENT_TYPE_REGULAR = 'regular';

    public const STUDENT_TYPE_SPECIAL = 'special';

    public const STUDENT_TYPE_SCHOLARSHIP = 'scholarship';

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date:Y-m-d',
            'admission_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getRollNumberAttribute(): ?int
    {
        return $this->attributes['roll_number'] ?? $this->currentAcademic?->roll_number;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicHistories(): HasMany
    {
        return $this->hasMany(StudentAcademicHistory::class);
    }

    public function currentAcademic(): HasOne
    {
        return $this->hasOne(StudentAcademicHistory::class)->where('is_current', true);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(StudentPhoto::class);
    }

    public function currentPhoto(): HasOne
    {
        return $this->hasOne(StudentPhoto::class)->where('is_current', true);
    }

    public function timelineLogs(): HasMany
    {
        return $this->hasMany(StudentTimelineLog::class);
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(StudentParent::class, 'student_id');
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_students', 'student_id', 'user_id')
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

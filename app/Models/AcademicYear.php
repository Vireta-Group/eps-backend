<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'year_name',
        'year_name_bn',
        'start_date',
        'end_date',
        'is_current',
        'year_status',
        'is_locked',
        'locked_at',
        'locked_by',
        'result_published',
        'promotion_completed',
        'fee_settled',
        'library_cleared',
        'copied_from_year_id',
        'copy_scope',
        'is_demo',
        'status',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
    ];

    public const YEAR_STATUS_UPCOMING = 'upcoming';

    public const YEAR_STATUS_ACTIVE = 'active';

    public const YEAR_STATUS_TRANSITIONING = 'transitioning';

    public const YEAR_STATUS_ARCHIVED = 'archived';

    public const COPY_SCOPE_CLASS_SECTION = 'class_section_only';

    public const COPY_SCOPE_CLASS_SECTION_SUBJECT = 'class_section_subject';

    public const COPY_SCOPE_CLASS_SECTION_SUBJECT_ROUTINE = 'class_section_subject_routine';

    public const COPY_SCOPE_ALL = 'all_except_students';

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_current' => 'boolean',
            'is_locked' => 'boolean',
            'result_published' => 'boolean',
            'promotion_completed' => 'boolean',
            'fee_settled' => 'boolean',
            'library_cleared' => 'boolean',
            'is_demo' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function copiedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'copied_from_year_id');
    }

    public function copiedYears(): HasMany
    {
        return $this->hasMany(self::class, 'copied_from_year_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scopeActive($query)
    {
        return $query->where('year_status', self::YEAR_STATUS_ACTIVE);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('year_status', self::YEAR_STATUS_UPCOMING);
    }

    public function scopeArchived($query)
    {
        return $query->where('year_status', self::YEAR_STATUS_ARCHIVED);
    }
}

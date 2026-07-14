<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAcademicHistory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'student_academic_histories';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'academic_year_id',
        'school_class_id',
        'school_section_id',
        'roll_number',
        'is_current',
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

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'is_demo' => 'boolean',
            'roll_number' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function schoolSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class);
    }
}

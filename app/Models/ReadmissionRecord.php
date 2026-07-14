<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadmissionRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'dropout_record_id',
        'readmission_date',
        'reason',
        'new_academic_year_id',
        'new_class_id',
        'new_section_id',
        'fee_settled',
        'remarks',
        'is_demo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'readmission_date' => 'date:Y-m-d',
            'fee_settled' => 'boolean',
            'is_demo' => 'boolean',
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

    public function dropoutRecord(): BelongsTo
    {
        return $this->belongsTo(DropoutRecord::class, 'dropout_record_id');
    }

    public function newAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'new_academic_year_id');
    }

    public function newClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'new_class_id');
    }

    public function newSection(): BelongsTo
    {
        return $this->belongsTo(SchoolSection::class, 'new_section_id');
    }
}

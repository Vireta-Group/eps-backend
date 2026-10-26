<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectExamConfig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'exam_type_id',
        'grading_system_id',
        'pass_marks',
        'is_mandatory',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pass_marks' => 'decimal:2',
            'is_mandatory' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
    }
}

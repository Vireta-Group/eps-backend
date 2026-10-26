<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMark extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'exam_schedule_id',
        'theory_marks',
        'practical_marks',
        'total_marks',
        'gpa',
        'grade',
        'status',
        'pass_marks',
        'entered_by',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'theory_marks' => 'decimal:2',
            'practical_marks' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'gpa' => 'decimal:2',
            'pass_marks' => 'decimal:2',
            'verified_at' => 'datetime',
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

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}
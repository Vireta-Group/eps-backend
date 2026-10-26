<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'exam_calendar_id',
        'exam_type_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'total_marks',
        'room_id',
        'invigilator_id',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date:Y-m-d',
            'total_marks' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ExamCalendar::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class);
    }
}

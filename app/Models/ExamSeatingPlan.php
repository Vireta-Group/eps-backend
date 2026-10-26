<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSeatingPlan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'exam_schedule_id',
        'student_id',
        'roll_number',
        'seat_number',
    ];

    protected function casts(): array
    {
        return [
            'seat_number' => 'string',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}

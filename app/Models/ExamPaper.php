<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamPaper extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'exam_schedule_id',
        'title',
        'set_code',
        'total_marks',
        'time_limit_minutes',
        'auto_generated',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'time_limit_minutes' => 'integer',
            'auto_generated' => 'boolean',
            'status' => 'string',
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

    public function questions(): HasMany
    {
        return $this->hasMany(PaperQuestion::class);
    }
}
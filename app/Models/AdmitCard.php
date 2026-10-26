<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmitCard extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'exam_schedule_id',
        'qr_code',
        'is_issued',
        'issued_at',
        'valid_from',
        'valid_to',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_issued' => 'boolean',
            'issued_at' => 'datetime',
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'status' => 'string',
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
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DropoutRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'dropout_date',
        'dropout_category',
        'reason',
        'last_attendance_date',
        'supporting_note',
        'fee_due_amount',
        'status',
        'is_demo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dropout_date' => 'date:Y-m-d',
            'last_attendance_date' => 'date:Y-m-d',
            'fee_due_amount' => 'decimal:2',
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

    public function readmissions(): HasMany
    {
        return $this->hasMany(ReadmissionRecord::class, 'dropout_record_id');
    }
}

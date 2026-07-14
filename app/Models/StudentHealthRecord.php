<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHealthRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'record_date',
        'height_cm',
        'weight_kg',
        'bmi',
        'bmi_status',
        'notes',
        'recorded_by',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'is_demo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date:Y-m-d',
            'height_cm' => 'decimal:1',
            'weight_kg' => 'decimal:1',
            'bmi' => 'decimal:1',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

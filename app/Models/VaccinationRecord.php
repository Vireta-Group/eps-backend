<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaccinationRecord extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'vaccine_name',
        'dose_number',
        'vaccination_date',
        'next_due_date',
        'healthcare_provider',
        'certificate_url',
        'notes',
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
            'vaccination_date' => 'date:Y-m-d',
            'next_due_date' => 'date:Y-m-d',
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
}

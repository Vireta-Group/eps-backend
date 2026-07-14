<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCheckup extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'checkup_date',
        'checkup_type',
        'doctor_name',
        'findings',
        'recommendations',
        'follow_up_date',
        'report_url',
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
            'checkup_date' => 'date:Y-m-d',
            'follow_up_date' => 'date:Y-m-d',
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

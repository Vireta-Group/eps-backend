<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTraining extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'training_name',
        'provider',
        'duration',
        'completion_date',
        'expiry_date',
        'certificate_url',
        'description',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
            'expiry_date' => 'date',
            'is_demo' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'title',
        'training_type',
        'description',
        'trainer_name',
        'trainer_type',
        'duration_minutes',
        'scheduled_at',
        'location',
        'meeting_link',
        'max_participants',
        'cost',
        'is_mandatory',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'scheduled_at' => 'datetime',
            'cost' => 'decimal:2',
            'is_mandatory' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TrainingCertificate::class);
    }
}

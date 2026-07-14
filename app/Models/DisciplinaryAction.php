<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryAction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'incident_id',
        'student_id',
        'action_date',
        'action_type',
        'description',
        'duration_days',
        'follow_up_date',
        'is_completed',
        'completed_at',
        'taken_by',
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
            'action_date' => 'date:Y-m-d',
            'follow_up_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'is_completed' => 'boolean',
            'is_demo' => 'boolean',
            'duration_days' => 'integer',
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

    public function incident(): BelongsTo
    {
        return $this->belongsTo(BehaviourIncident::class, 'incident_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}

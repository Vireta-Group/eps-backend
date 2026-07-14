<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BehaviourIncident extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'incident_date',
        'incident_time',
        'location',
        'incident_type',
        'description',
        'severity',
        'involved_students',
        'witnesses',
        'evidence_urls',
        'reported_by',
        'status',
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
            'incident_date' => 'date:Y-m-d',
            'incident_time' => 'datetime:H:i',
            'involved_students' => 'array',
            'witnesses' => 'array',
            'evidence_urls' => 'array',
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function disciplinaryActions(): HasMany
    {
        return $this->hasMany(DisciplinaryAction::class, 'incident_id');
    }
}

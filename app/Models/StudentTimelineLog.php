<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTimelineLog extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $table = 'student_timeline_logs';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'event_type',
        'description',
        'reference_type',
        'reference_id',
        'metadata',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'created_at' => 'datetime',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherFingerprint extends Model
{
    use HasFactory, HasUuids;

    public const FINGER_INDEXES = [1, 2];

    protected $table = 'teacher_fingerprints';

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'device_id',
        'finger_index',
        'template_data',
        'label',
        'is_active',
        'enrolled_at',
        'enrolled_by',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'template_data',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'finger_index' => 'integer',
            'is_active' => 'boolean',
            'enrolled_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }
}

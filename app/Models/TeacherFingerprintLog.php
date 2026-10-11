<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherFingerprintLog extends Model
{
    use HasFactory, HasUuids;

    public const DIRECTION_ENTRY = 'entry';

    public const DIRECTION_EXIT = 'exit';

    public const DIRECTIONS = [
        self::DIRECTION_ENTRY,
        self::DIRECTION_EXIT,
    ];

    protected $table = 'teacher_fingerprint_logs';

    protected $fillable = [
        'tenant_id',
        'device_id',
        'teacher_id',
        'punch_time',
        'direction',
        'raw_data',
        'processed',
        'processed_at',
        'synced',
        'synced_at',
        'is_demo',
    ];

    protected $hidden = [
        'raw_data',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
            'processed' => 'boolean',
            'processed_at' => 'datetime',
            'synced' => 'boolean',
            'synced_at' => 'datetime',
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

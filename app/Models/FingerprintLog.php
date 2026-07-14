<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fingerprint_logs';

    protected $fillable = [
        'tenant_id',
        'device_id',
        'student_id',
        'scan_time',
        'direction',
        'raw_data',
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
            'scan_time' => 'datetime',
            'synced' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'device_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

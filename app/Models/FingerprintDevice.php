<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintDevice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'fingerprint_devices';

    protected $fillable = [
        'tenant_id',
        'device_name',
        'device_model',
        'serial_number',
        'location',
        'ip_address',
        'port',
        'shift_type',
        'status',
        'is_online',
        'last_sync_at',
        'remarks',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

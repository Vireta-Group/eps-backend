<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantAddress extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'address_type',
        'division',
        'district',
        'upazila',
        'post_office',
        'post_code',
        'village_area',
        'full_address',
        'latitude',
        'longitude',
        'google_map_url',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

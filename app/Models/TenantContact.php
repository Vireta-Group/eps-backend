<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantContact extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'contact_type',
        'name_bn',
        'name_en',
        'designation',
        'mobile',
        'email',
        'is_primary',
        'signature_url',
        'photo_url',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

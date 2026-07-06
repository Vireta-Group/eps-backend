<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBranding extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'logo_primary_url',
        'logo_landscape_url',
        'logo_dark_url',
        'favicon_url',
        'font_family',
        'custom_css',
        'status',
        'is_demo',
        'created_by',
        'updated_by',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

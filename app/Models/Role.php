<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_system',
        'parent_role_id',
        'display_order',
        'status',
        'is_demo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_demo' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['scope_type', 'scope_id', 'assigned_at', 'status'])
            ->withTimestamps();
    }
}

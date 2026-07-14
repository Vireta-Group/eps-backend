<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdCardTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'id_card_templates';

    protected $fillable = [
        'tenant_id',
        'template_name',
        'front_layout',
        'back_layout',
        'card_width',
        'card_height',
        'font_family',
        'color_theme',
        'is_default',
        'is_active',
        'is_demo',
        'status',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'front_layout' => 'json',
            'back_layout' => 'json',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

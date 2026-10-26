<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollProcess extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'month',
        'year',
        'status',
        'processed_at',
        'locked_at',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}

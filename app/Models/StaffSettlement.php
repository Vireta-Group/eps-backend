<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffSettlement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'staff_id',
        'staff_type',
        'last_working_day',
        'components',
        'gross_amount',
        'deductions',
        'total_deductions',
        'net_amount',
        'status',
        'payment_method',
        'paid_at',
        'receipt_no',
        'approved_by',
        'approved_at',
        'remarks',
        'is_demo',
        'created_by',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'last_working_day' => 'date:Y-m-d',
            'components' => 'array',
            'deductions' => 'array',
            'gross_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function staff(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

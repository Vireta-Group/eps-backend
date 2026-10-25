<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffLeaveBalance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'staff_id',
        'staff_type',
        'leave_type_id',
        'academic_year_id',
        'total_days',
        'used_days',
        'remaining_days',
        'is_demo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'remaining_days' => 'decimal:1',
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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(StaffLeaveType::class, 'leave_type_id');
    }
}

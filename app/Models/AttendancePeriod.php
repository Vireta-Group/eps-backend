<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePeriod extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'attendance_periods';

    protected $fillable = [
        'tenant_id',
        'period_name',
        'period_name_bn',
        'start_time',
        'end_time',
        'is_active',
        'display_order',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

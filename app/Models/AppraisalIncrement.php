<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalIncrement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'performance_review_id',
        'staff_id',
        'staff_type',
        'previous_basic',
        'increment_amount',
        'new_basic',
        'percentage_increase',
        'effective_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_basic' => 'decimal:2',
            'increment_amount' => 'decimal:2',
            'new_basic' => 'decimal:2',
            'percentage_increase' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

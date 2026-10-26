<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PfContribution extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'staff_id',
        'staff_type',
        'employee_contribution',
        'employer_contribution',
        'total_contribution',
        'month',
        'year',
        'contribution_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'total_contribution' => 'decimal:2',
            'contribution_date' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

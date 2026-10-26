<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payroll_process_id',
        'staff_id',
        'staff_type',
        'basic_salary',
        'earnings',
        'deductions',
        'gross_salary',
        'taxable_income',
        'tax_amount',
        'total_deductions',
        'net_salary',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'earnings' => 'array',
            'deductions' => 'array',
            'basic_salary' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(PayrollProcess::class, 'payroll_process_id');
    }
}

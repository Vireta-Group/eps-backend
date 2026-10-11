<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'salary_structures';

    protected $fillable = [
        'tenant_id',
        'structure_name',
        'structure_name_bn',
        'description',
        'basic_pay',
        'house_rent',
        'medical_allowance',
        'transport_allowance',
        'other_allowances',
        'total',
        'is_active',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'house_rent' => 'decimal:2',
            'medical_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowances' => 'array',
            'total' => 'decimal:2',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

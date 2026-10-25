<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'document_type',
        'document_name',
        'file_url',
        'expiry_date',
        'verification_status',
        'remarks',
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
            'expiry_date' => 'date:Y-m-d',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

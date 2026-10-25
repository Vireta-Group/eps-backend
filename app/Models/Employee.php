<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_id',
        'designation',
        'department_id',
        'employment_type',
        'reporting_manager_id',
        'work_location',
        'joining_date',
        'probation_end_date',
        'confirmation_date',
        'salary_grade',
        'bank_info',
        'tin_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
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
            'joining_date' => 'date:Y-m-d',
            'probation_end_date' => 'date:Y-m-d',
            'confirmation_date' => 'date:Y-m-d',
            'bank_info' => 'array',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}

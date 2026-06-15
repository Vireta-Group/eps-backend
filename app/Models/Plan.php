<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'price_quarterly',
        'setup_fee',
        'currency',
        'trial_days',
        'max_students',
        'max_teachers',
        'max_staff',
        'max_branches',
        'storage_gb',
        'sms_per_month',
        'api_calls_per_day',
        'features',
        'is_custom',
        'display_order',
        'status',
        'is_demo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'price_quarterly' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'storage_gb' => 'decimal:2',
            'features' => 'array',
            'is_custom' => 'boolean',
            'is_demo' => 'boolean',
            'display_order' => 'integer',
            'trial_days' => 'integer',
            'max_students' => 'integer',
            'max_teachers' => 'integer',
            'max_staff' => 'integer',
            'max_branches' => 'integer',
            'sms_per_month' => 'integer',
            'api_calls_per_day' => 'integer',
        ];
    }
}

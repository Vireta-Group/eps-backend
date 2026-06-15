<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name_bn',
        'name_en',
        'name_short',
        'slug',
        'subdomain',
        'custom_domain',
        'logo_url',
        'school_type',
        'eiin_number',
        'board_affiliation',
        'mpo_status',
        'mpo_index',
        'established_year',
        'tin_number',
        'email',
        'phone_primary',
        'phone_secondary',
        'whatsapp_number',
        'website_url',
        'current_plan_id',
        'tenant_status',
        'trial_ends_at',
        'timezone',
        'default_language',
        'currency',
        'academic_year_start',
        'date_format',
        'max_students',
        'max_teachers',
        'max_staff',
        'max_branches',
        'storage_gb',
        'sms_per_month',
        'onboarding_step',
        'onboarding_done',
        'settings',
        'status',
        'is_demo',
        'demo_expires_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'mpo_status' => 'boolean',
            'onboarding_done' => 'boolean',
            'is_demo' => 'boolean',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'demo_expires_at' => 'datetime',
            'established_year' => 'integer',
            'academic_year_start' => 'integer',
            'max_students' => 'integer',
            'max_teachers' => 'integer',
            'max_staff' => 'integer',
            'max_branches' => 'integer',
            'sms_per_month' => 'integer',
            'onboarding_step' => 'integer',
            'storage_gb' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(TenantAddress::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(TenantContact::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

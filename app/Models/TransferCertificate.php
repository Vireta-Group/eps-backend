<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferCertificate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'student_id',
        'tc_number',
        'issue_date',
        'last_attendance_date',
        'conduct',
        'leaving_reason',
        'leaving_reason_category',
        'remarks',
        'checklist_cleared',
        'is_duplicate',
        'original_tc_id',
        'previous_school_name',
        'previous_school_address',
        'received_date',
        'scan_url',
        'is_received',
        'status',
        'cancelled_at',
        'cancel_reason',
        'is_demo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'last_attendance_date' => 'date:Y-m-d',
            'received_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
            'checklist_cleared' => 'boolean',
            'is_duplicate' => 'boolean',
            'is_received' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function originalTc(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_tc_id');
    }

    public function duplicateTcs(): HasMany
    {
        return $this->hasMany(self::class, 'original_tc_id');
    }
}

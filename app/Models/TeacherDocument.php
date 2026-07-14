<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
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
        'created_at',
        'updated_at',
        'is_demo',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'is_demo' => 'boolean',
        ];
    }

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

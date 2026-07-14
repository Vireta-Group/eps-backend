<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentGuardian extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'student_guardians';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'guardian_type',
        'name_en',
        'name_bn',
        'occupation',
        'mobile',
        'email',
        'nid_number',
        'address',
        'photo_url',
        'is_primary',
        'is_demo',
        'status',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by',
        'delete_reason',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    public const GUARDIAN_TYPE_FATHER = 'father';

    public const GUARDIAN_TYPE_MOTHER = 'mother';

    public const GUARDIAN_TYPE_LOCAL = 'local_guardian';

    public const GUARDIAN_TYPE_CUSTOM = 'custom';

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
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
}

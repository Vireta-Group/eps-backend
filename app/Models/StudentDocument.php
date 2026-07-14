<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDocument extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'student_documents';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'document_type',
        'document_name',
        'file_url',
        'issue_date',
        'expiry_date',
        'remarks',
        'status',
        'is_demo',
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

    public const DOCUMENT_BIRTH_CERT = 'birth_certificate';

    public const DOCUMENT_TC = 'transfer_certificate';

    public const DOCUMENT_MARKSHEET = 'previous_marksheet';

    public const DOCUMENT_NID = 'nid';

    public const DOCUMENT_PASSPORT = 'passport';

    public const DOCUMENT_MEDICAL = 'medical_certificate';

    public const DOCUMENT_DISABILITY = 'disability_certificate';

    public const DOCUMENT_PHOTO = 'photo';

    public const DOCUMENT_CUSTOM = 'custom';

    protected function casts(): array
    {
        return [
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSeparationDocument extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'teacher_separation_documents';

    protected $fillable = [
        'tenant_id',
        'separation_id',
        'document_type',
        'title',
        'file_url',
        'notes',
        'document_date',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const TYPE_CAUSE = 'cause';

    public const TYPE_WARNING_LETTER = 'warning_letter';

    public const TYPE_SHOW_CAUSE_NOTICE = 'show_cause_notice';

    public const TYPE_SHOW_CAUSE_REPLY = 'show_cause_reply';

    public const TYPE_FINAL_DECISION = 'final_decision';

    public const TYPE_TERMINATION_LETTER = 'termination_letter';

    public const TYPE_RESIGNATION_RECEIPT = 'resignation_receipt';

    public const TYPES = [
        self::TYPE_CAUSE,
        self::TYPE_WARNING_LETTER,
        self::TYPE_SHOW_CAUSE_NOTICE,
        self::TYPE_SHOW_CAUSE_REPLY,
        self::TYPE_FINAL_DECISION,
        self::TYPE_TERMINATION_LETTER,
        self::TYPE_RESIGNATION_RECEIPT,
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date:Y-m-d',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function separation(): BelongsTo
    {
        return $this->belongsTo(TeacherSeparation::class, 'separation_id');
    }
}

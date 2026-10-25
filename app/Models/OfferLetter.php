<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferLetter extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'candidate_application_id',
        'offered_salary',
        'joining_date',
        'benefits',
        'status',
        'accepted_at',
        'digital_signature_url',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'updated_at',
        'is_demo',
    ];

    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'offered_salary' => 'decimal:2',
            'joining_date' => 'date',
            'benefits' => 'array',
            'accepted_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }
}

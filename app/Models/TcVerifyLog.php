<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TcVerifyLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'transfer_certificate_id',
        'tc_number',
        'verified_by_ip',
        'verified_by_user_id',
        'verified_at',
        'is_valid',
        'verification_data',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_valid' => 'boolean',
            'is_demo' => 'boolean',
            'verification_data' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transferCertificate(): BelongsTo
    {
        return $this->belongsTo(TransferCertificate::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}

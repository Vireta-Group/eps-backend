<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPhoto extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'student_photos';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'photo_url',
        'is_current',
        'archived_at',
        'is_demo',
        'created_by',
    ];

    protected $hidden = [
        'created_by',
        'created_at',
        'updated_at',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'archived_at' => 'datetime',
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

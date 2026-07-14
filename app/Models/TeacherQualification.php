<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherQualification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'degree',
        'subject',
        'institution',
        'board',
        'result',
        'passing_year',
        'certificate_url',
        'status',
        'is_highest',
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
            'passing_year' => 'integer',
            'is_highest' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

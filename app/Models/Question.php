<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'question_text',
        'question_type',
        'marks',
        'negative_marking',
        'answer_guide',
        'explanation',
        'difficulty',
        'bloom_level',
        'source',
        'language',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'negative_marking' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
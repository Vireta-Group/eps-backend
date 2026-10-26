<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'grading_system_id',
        'grade_name',
        'min_mark',
        'max_mark',
        'gpa_point',
        'grade_color',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'min_mark' => 'decimal:2',
            'max_mark' => 'decimal:2',
            'gpa_point' => 'decimal:2',
        ];
    }

    public function gradingSystem(): BelongsTo
    {
        return $this->belongsTo(GradingSystem::class);
    }
}

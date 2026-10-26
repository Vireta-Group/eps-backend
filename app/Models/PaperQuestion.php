<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperQuestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'exam_paper_id',
        'question_id',
        'sequence',
        'marks',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
        ];
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(ExamPaper::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
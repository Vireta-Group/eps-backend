<?php

namespace App\Services\Exam;

use App\Models\GradingSystem;
use App\Models\GradingRule;
use Illuminate\Support\Facades\Log;

class GradingService
{
    /**
     * Convert a numeric mark into a grade and GPA point.
     * 
     * @param float $mark
     * @param GradingSystem $system
     * @return array
     */
    public function calculateGrade(float $mark, GradingSystem $system): array
    {
        $rule = GradingRule::where('grading_system_id', $system->id)
            ->where('min_mark', '<=', $mark)
            ->where('max_mark', '>=', $mark)
            ->first();

        if (!$rule) {
            return [
                'grade' => 'F',
                'gpa' => 0.00,
                'color' => '#FF0000',
                'description' => 'Failed'
            ];
        }

        return [
            'grade' => $rule->grade_name,
            'gpa' => $rule->gpa_point,
            'color' => $rule->grade_color,
            'description' => $rule->description,
        ];
    }

    /**
     * Determine if a student passed based on the mark and the config.
     * 
     * @param float $mark
     * @param float $passMarks
     * @return bool
     */
    public function isPassed(float $mark, float $passMarks): bool
    {
        return $mark >= $passMarks;
    }
}

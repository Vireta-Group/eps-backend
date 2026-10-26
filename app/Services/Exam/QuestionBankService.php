<?php

namespace App\Services\Exam;

use App\Models\ExamPaper;
use App\Models\PaperQuestion;
use App\Models\Question;
use Illuminate\Support\Collection;

class QuestionBankService
{
    public function generatePaper(string $tenantId, array $data): ExamPaper
    {
        $scheduleId = $data['exam_schedule_id'];
        $subjectId = $data['subject_id'];
        $totalMarks = (float) ($data['total_marks'] ?? 0);
        $questionCount = (int) ($data['question_count'] ?? 0);
        $difficultyDistribution = $data['difficulty_distribution'] ?? [
            'easy' => 20,
            'medium' => 50,
            'hard' => 30,
        ];

        $difficultyMap = [
            'easy' => ['easy', 'easy'],
            'medium' => ['medium', 'medium'],
            'hard' => ['hard', 'hard'],
        ];

        $selectedQuestions = collect();

        if ($questionCount > 0) {
            $perDifficulty = max(1, (int) round($questionCount * ($difficultyDistribution['easy'] / 100)));
            $mediumCount = max(1, (int) round($questionCount * ($difficultyDistribution['medium'] / 100)));
            $hardCount = max(1, (int) round($questionCount * ($difficultyDistribution['hard'] / 100)));

            $easyQuestions = Question::where('tenant_id', $tenantId)
                ->where('subject_id', $subjectId)
                ->where('difficulty', 'easy')
                ->where('is_active', true)
                ->orderByRandom()
                ->limit($perDifficulty)
                ->get();

            $mediumQuestions = Question::where('tenant_id', $tenantId)
                ->where('subject_id', $subjectId)
                ->where('difficulty', 'medium')
                ->where('is_active', true)
                ->orderByRandom()
                ->limit($mediumCount)
                ->get();

            $hardQuestions = Question::where('tenant_id', $tenantId)
                ->where('subject_id', $subjectId)
                ->where('difficulty', 'hard')
                ->where('is_active', true)
                ->orderByRandom()
                ->limit($hardCount)
                ->get();

            $selectedQuestions = $easyQuestions->concat($mediumQuestions)->concat($hardQuestions);
        } else {
            $selectedQuestions = Question::where('tenant_id', $tenantId)
                ->where('subject_id', $subjectId)
                ->where('is_active', true)
                ->orderByRandom()
                ->limit($questionCount ?: 10)
                ->get();
        }

        $paper = ExamPaper::create([
            'tenant_id' => $tenantId,
            'exam_schedule_id' => $scheduleId,
            'title' => 'Paper - ' . now()->format('Y-m-d'),
            'set_code' => strtoupper(chr(65 + rand(0, 25))),
            'total_marks' => $totalMarks,
            'time_limit_minutes' => 60,
            'auto_generated' => true,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($selectedQuestions as $index => $question) {
            PaperQuestion::create([
                'exam_paper_id' => $paper->id,
                'question_id' => $question->id,
                'sequence' => $index + 1,
                'marks' => $question->marks,
            ]);
        }

        $paper->update(['total_marks' => $selectedQuestions->sum('marks')]);

        return $paper;
    }
}
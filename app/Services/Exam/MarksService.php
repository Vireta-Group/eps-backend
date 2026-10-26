<?php

namespace App\Services\Exam;

use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\GradingSystem;
use App\Models\StudentMark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MarksService
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * Enter or update marks for a set of students.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function enterMarks(string $tenantId, ExamSchedule $schedule, array $rows, string $enteredBy): Collection
    {
        return DB::transaction(function () use ($tenantId, $schedule, $rows, $enteredBy) {
            $marks = collect();

            foreach ($rows as $row) {
                $theory = (float) ($row['theory_marks'] ?? 0);
                $practical = (float) ($row['practical_marks'] ?? 0);

                $marks->push(StudentMark::updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'student_id' => $row['student_id'],
                        'exam_schedule_id' => $schedule->id,
                    ],
                    [
                        'theory_marks' => $theory,
                        'practical_marks' => $practical,
                        'total_marks' => $theory + $practical,
                        'pass_marks' => $row['pass_marks'] ?? null,
                        'status' => 'entered',
                        'entered_at' => now(),
                        'entered_by' => $enteredBy,
                    ]
                ));
            }

            return $marks;
        });
    }

    /**
     * Mark a single mark entry as verified.
     */
    public function verifyMark(StudentMark $mark, string $userId): StudentMark
    {
        $mark->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $userId,
        ]);

        return $mark;
    }

    /**
     * Apply grading rules to obtain the GPA and grade of a mark.
     */
    public function applyGrading(StudentMark $mark, GradingSystem $system): StudentMark
    {
        $result = $this->gradingService->calculateGrade((float) $mark->total_marks, $system);

        $mark->update([
            'gpa' => $result['gpa'],
            'grade' => $result['grade'],
        ]);

        return $mark;
    }

    /**
     * Publish results for the given students.
     *
     * @param  array<int, string>  $studentIds
     */
    public function publishResults(string $tenantId, string $examPeriod, array $studentIds, string $userId): Collection
    {
        $results = collect();

        foreach ($studentIds as $studentId) {
            $marks = StudentMark::where('tenant_id', $tenantId)
                ->where('student_id', $studentId)
                ->where('status', 'verified')
                ->get();

            if ($marks->isEmpty()) {
                continue;
            }

            $averageGpa = round((float) $marks->avg('gpa'), 2);
            $hasFailedSubject = $marks->contains(fn (StudentMark $mark) => $mark->grade === 'F');

            $results->push(ExamResult::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'student_id' => $studentId,
                    'exam_period' => $examPeriod,
                ],
                [
                    'total_gpa' => $averageGpa,
                    'final_grade' => $hasFailedSubject ? 'F' : $this->gradeFromGpa($averageGpa),
                    'result_status' => $hasFailedSubject ? 'failed' : 'passed',
                    'published_at' => now(),
                    'published_by' => $userId,
                    'created_by' => $userId,
                ]
            ));
        }

        return $results;
    }

    /**
     * Map a GPA value to a letter grade.
     */
    protected function gradeFromGpa(float $gpa): string
    {
        return match (true) {
            $gpa >= 5.0 => 'A+',
            $gpa >= 4.0 => 'A',
            $gpa >= 3.5 => 'A-',
            $gpa >= 3.0 => 'B',
            $gpa >= 2.0 => 'C',
            $gpa >= 1.0 => 'D',
            default => 'F',
        };
    }
}

<?php

namespace App\Services\Exam;

use App\Models\AdmitCard;
use App\Models\ExamSchedule;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdmitCardService
{
    /**
     * Check whether a student is eligible to receive an admit card.
     *
     * @return array{eligible: bool, reasons: array<int, string>}
     */
    public function checkEligibility(Student $student, ExamSchedule $schedule): array
    {
        $reasons = [];

        if (! $student->is_active) {
            $reasons[] = 'Student is inactive.';
        }

        if ($schedule->exam_date && $schedule->exam_date->isPast()) {
            $reasons[] = 'Exam date has already passed.';
        }

        return [
            'eligible' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Issue an admit card for a student.
     */
    public function generateForStudent(string $tenantId, Student $student, ExamSchedule $schedule): AdmitCard
    {
        return AdmitCard::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'student_id' => $student->id,
                'exam_schedule_id' => $schedule->id,
            ],
            [
                'qr_code' => (string) Str::uuid(),
                'is_issued' => true,
                'issued_at' => now(),
                'valid_from' => $schedule->exam_date,
                'valid_to' => $schedule->exam_date,
                'status' => 'issued',
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * Issue admit cards for every eligible student of a schedule.
     *
     * @param  array<int, string>|null  $studentIds
     */
    public function bulkGenerate(string $tenantId, ExamSchedule $schedule, ?array $studentIds = null): Collection
    {
        $query = Student::where('tenant_id', $tenantId)->where('is_active', true);

        if (! empty($studentIds)) {
            $query->whereIn('id', $studentIds);
        }

        return $query->get()->map(
            fn (Student $student) => $this->generateForStudent($tenantId, $student, $schedule)
        );
    }

    /**
     * Verify an admit card by its QR code.
     */
    public function verify(string $qrCode): ?AdmitCard
    {
        return AdmitCard::where('qr_code', $qrCode)
            ->where('status', 'issued')
            ->with(['student', 'schedule'])
            ->first();
    }
}

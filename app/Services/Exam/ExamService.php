<?php

namespace App\Services\Exam;

use App\Models\ExamCalendar;
use App\Models\ExamRoom;
use App\Models\ExamSchedule;
use App\Models\ExamSeatingPlan;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Support\Collection;

class ExamService
{
    public function getCalendars(string $tenantId): Collection
    {
        return ExamCalendar::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('start_date')
            ->get();
    }

    public function createCalendar(string $tenantId, array $data): ExamCalendar
    {
        return ExamCalendar::create(array_merge($data, [
            'tenant_id' => $tenantId,
            'created_by' => auth()->id(),
        ]));
    }

    public function getSchedules(string $tenantId, ?string $examCalendarId = null): Collection
    {
        $query = ExamSchedule::where('tenant_id', $tenantId);

        if ($examCalendarId) {
            $query->where('exam_calendar_id', $examCalendarId);
        }

        return $query->with(['examType', 'subject', 'room'])->orderBy('exam_date')->get();
    }

    public function createSchedule(string $tenantId, array $data): ExamSchedule
    {
        return ExamSchedule::create(array_merge($data, ['tenant_id' => $tenantId]));
    }

    public function getRooms(string $tenantId): Collection
    {
        return ExamRoom::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function createRoom(string $tenantId, array $data): ExamRoom
    {
        return ExamRoom::create(array_merge($data, ['tenant_id' => $tenantId]));
    }

    public function autoAllocateSeating(ExamSchedule $schedule, string $tenantId): Collection
    {
        $students = Student::where('tenant_id', $tenantId)
            ->whereHas('academicHistories', fn ($query) => $query->where('school_class_id', $schedule->subject?->school_class_id ?? null))
            ->get();

        $plan = ExamSeatingPlan::where('exam_schedule_id', $schedule->id)->first();

        $seatNumber = 1;
        foreach ($students as $student) {
            if ($plan) {
                ExamSeatingPlan::updateOrCreate(
                    [
                        'exam_schedule_id' => $schedule->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'roll_number' => $student->roll_no ?? $student->id,
                        'seat_number' => (string) $seatNumber,
                    ]
                );
            } else {
                ExamSeatingPlan::create([
                    'tenant_id' => $tenantId,
                    'exam_schedule_id' => $schedule->id,
                    'student_id' => $student->id,
                    'roll_number' => $student->roll_no ?? (string) $student->id,
                    'seat_number' => (string) $seatNumber,
                ]);
            }

            $seatNumber++;
        }

        return ExamSeatingPlan::where('exam_schedule_id', $schedule->id)->get();
    }
}

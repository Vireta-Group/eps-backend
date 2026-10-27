<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamCalendar;
use App\Models\ExamRoom;
use App\Models\ExamSchedule;
use App\Models\ExamSeatingPlan;
use App\Services\Exam\ExamService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Exam Management', description: 'Exam configuration, schedules, seating, question bank, papers, admit cards, marks, and results.', weight: 100)]
class ExamManagementController extends Controller
{
    public function __construct(
        protected ExamService $examService
    ) {}

    /**
     * List all exam calendars.
     * 
     * @operationId exam.calendars.index
     */
    public function indexCalendars(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $calendars = $this->examService->getCalendars($tenant->id);

        return response()->json([
            'status' => 'success',
            'data' => $calendars,
        ]);
    }

    /**
     * Create a new exam calendar.
     * 
     * @operationId exam.calendars.store
     */
    public function storeCalendar(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'result_publish_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $calendar = $this->examService->createCalendar($tenant->id, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Exam calendar created successfully.',
            'data' => $calendar,
        ], 201);
    }

    /**
     * List all exam schedules.
     * 
     * @operationId exam.schedules.index
     */
    public function indexSchedules(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $schedules = $this->examService->getSchedules($tenant->id, $request->query('exam_calendar_id'));

        return response()->json([
            'status' => 'success',
            'data' => $schedules,
        ]);
    }

    /**
     * Create a new exam schedule.
     * 
     * @operationId exam.schedules.store
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'exam_calendar_id' => ['required', 'uuid', 'exists:exam_calendars,id'],
            'exam_type_id' => ['required', 'uuid', 'exists:exam_types,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'string', 'max:8'],
            'end_time' => ['required', 'string', 'max:8'],
            'total_marks' => ['required', 'numeric', 'min:0'],
            'room_id' => ['nullable', 'uuid', 'exists:exam_rooms,id'],
            'invigilator_id' => ['nullable', 'uuid'],
        ]);

        $schedule = $this->examService->createSchedule($tenant->id, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Exam schedule created successfully.',
            'data' => $schedule,
        ], 201);
    }

    /**
     * List all exam rooms.
     * 
     * @operationId exam.rooms.index
     */
    public function indexRooms(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $rooms = $this->examService->getRooms($tenant->id);

        return response()->json([
            'status' => 'success',
            'data' => $rooms,
        ]);
    }

    /**
     * Create a new exam room.
     * 
     * @operationId exam.rooms.store
     */
    public function storeRoom(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:0'],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'facilities' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $room = $this->examService->createRoom($tenant->id, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Exam room created successfully.',
            'data' => $room,
        ], 201);
    }

    /**
     * Auto-allocate seating plans for a schedule.
     * 
     * @operationId exam.seating.autoAllocate
     */
    public function autoAllocateSeating(Request $request, ExamSchedule $schedule): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($schedule->tenant_id !== $tenant->id) {
            abort(404);
        }

        $plan = $this->examService->autoAllocateSeating($schedule, $tenant->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Seating plans auto-allocated successfully.',
            'data' => $plan,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\InterviewFeedback;
use App\Models\InterviewSchedule;
use App\Models\JobPost;
use App\Models\OfferLetter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Recruitment & Onboarding > Recruitment', description: 'Manage job posts, candidate applications, interviews, and offer letters.', weight: 66)]
class RecruitmentController extends Controller
{
    /**
     * List all job posts for the tenant.
     *
     * @operationId recruitment.jobsIndex
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = JobPost::where('tenant_id', $tenant->id)
            ->with('department');

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Create a new job post.
     *
     * @operationId recruitment.jobsStore
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'job_title' => ['required', 'string', 'max:200'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'job_type' => ['required', 'string', 'max:50'],
            'vacancies' => ['required', 'integer', 'min:1'],
            'job_description' => ['required', 'string'],
            'responsibilities' => ['nullable', 'array'],
            'required_qualifications' => ['nullable', 'array'],
            'preferred_qualifications' => ['nullable', 'array'],
            'required_skills' => ['nullable', 'array'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'age_limit' => ['nullable', 'integer', 'min:18'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'application_deadline' => ['nullable', 'date'],
            'joining_date' => ['nullable', 'date'],
            'is_internal_only' => ['nullable', 'boolean'],
        ]);

        $jobPost = JobPost::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Job post created successfully.',
            'data' => $jobPost,
        ], 201);
    }

    /**
     * Submit a candidate application.
     *
     * @operationId recruitment.apply
     */
    public function apply(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'job_post_id' => ['required', 'uuid', 'exists:job_posts,id'],
            'candidate_name' => ['required', 'string', 'max:200'],
            'candidate_email' => ['required', 'email'],
            'candidate_phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'educational_qualifications' => ['nullable', 'array'],
            'work_experience' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'expected_salary' => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['nullable', 'integer', 'min:0'],
            'cv_url' => ['required', 'string'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        $jobPost = JobPost::where('tenant_id', $tenant->id)->where('id', $validated['job_post_id'])->firstOrFail();

        if (!$jobPost->is_active || ($jobPost->application_deadline && now()->gt($jobPost->application_deadline))) {
            return response()->json([
                'status' => 'error',
                'message' => 'This job post is no longer accepting applications.',
            ], 422);
        }

        $application = CandidateApplication::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'status' => CandidateApplication::STATUS_APPLIED,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Application submitted successfully.',
            'data' => $application,
        ], 201);
    }

    /**
     * List applications for a job post.
     *
     * @operationId recruitment.applicationsIndex
     */
    public function applications(Request $request, JobPost $jobPost): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($jobPost->tenant_id !== $tenant->id) abort(404);

        $query = CandidateApplication::where('tenant_id', $tenant->id)
            ->where('job_post_id', $jobPost->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Update application status (Shortlist, Reject, etc).
     *
     * @operationId recruitment.updateStatus
     */
    public function updateStatus(Request $request, CandidateApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($application->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                CandidateApplication::STATUS_UNDER_REVIEW,
                CandidateApplication::STATUS_SHORTLISTED,
                CandidateApplication::STATUS_INTERVIEW_SCHEDULED,
                CandidateApplication::STATUS_SELECTED,
                CandidateApplication::STATUS_REJECTED,
                CandidateApplication::STATUS_ON_HOLD,
            ])],
        ]);

        $application->update(['status' => $validated['status']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Application status updated.',
            'data' => $application->fresh(),
        ]);
    }

    /**
     * Schedule an interview.
     *
     * @operationId recruitment.scheduleInterview
     */
    public function scheduleInterview(Request $request, CandidateApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($application->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'round_number' => ['required', 'integer', 'min:1'],
            'interview_type' => ['required', 'string', 'max:50'],
            'scheduled_at' => ['required', 'date'],
            'meeting_link' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'interviewer_ids' => ['required', 'array'],
            'interviewer_ids.*' => ['uuid', 'exists:users,id'],
        ]);

        $schedule = InterviewSchedule::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'candidate_application_id' => $application->id,
            'status' => InterviewSchedule::STATUS_SCHEDULED,
            'created_by' => $request->user()->id,
        ]));

        // Update application status to interview_scheduled
        $application->update(['status' => CandidateApplication::STATUS_INTERVIEW_SCHEDULED]);

        return response()->json([
            'status' => 'success',
            'message' => 'Interview scheduled successfully.',
            'data' => $schedule,
        ], 201);
    }

    /**
     * Submit interview feedback.
     *
     * @operationId recruitment.submitFeedback
     */
    public function submitFeedback(Request $request, InterviewSchedule $schedule): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($schedule->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'communication_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'technical_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'problem_solving_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'attitude_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'overall_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'detailed_comments' => ['nullable', 'string'],
            'recommendation' => ['required', Rule::in([InterviewFeedback::RECOMMENDATION_HIRE, InterviewFeedback::RECOMMENDATION_HOLD, InterviewFeedback::RECOMMENDATION_REJECT])],
            'is_confidential' => ['nullable', 'boolean'],
        ]);

        $feedback = InterviewFeedback::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'interview_schedule_id' => $schedule->id,
            'interviewer_id' => $request->user()->id,
            'is_demo' => false,
        ]));

        $schedule->update(['status' => InterviewSchedule::STATUS_COMPLETED]);

        return response()->json([
            'status' => 'success',
            'message' => 'Interview feedback submitted.',
            'data' => $feedback,
        ], 201);
    }

    /**
     * Generate and send an offer letter.
     *
     * @operationId recruitment.sendOffer
     */
    public function sendOffer(Request $request, CandidateApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($application->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'offered_salary' => ['required', 'numeric', 'min:0'],
            'joining_date' => ['required', 'date'],
            'benefits' => ['nullable', 'array'],
        ]);

        $offer = OfferLetter::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'candidate_application_id' => $application->id,
            'status' => OfferLetter::STATUS_SENT,
            'created_by' => $request->user()->id,
        ]));

        $application->update(['status' => CandidateApplication::STATUS_SELECTED]);

        return response()->json([
            'status' => 'success',
            'message' => 'Offer letter generated and sent.',
            'data' => $offer,
        ], 201);
    }

    /**
     * Accept or reject an offer.
     *
     * @operationId recruitment.offerResponse
     */
    public function offerResponse(Request $request, OfferLetter $offer): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($offer->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([OfferLetter::STATUS_ACCEPTED, OfferLetter::STATUS_REJECTED])],
            'digital_signature_url' => ['nullable', 'string'],
            'accepted_at' => ['nullable', 'date'],
        ]);

        $offer->update([
            'status' => $validated['status'],
            'digital_signature_url' => $validated['digital_signature_url'] ?? null,
            'accepted_at' => $validated['accepted_at'] ?? now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Offer ' . $validated['status'] . ' successfully.',
            'data' => $offer->fresh(),
        ]);
    }
}

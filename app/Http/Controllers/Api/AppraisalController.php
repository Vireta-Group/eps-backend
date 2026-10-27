<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KpiDefinition;
use App\Models\PerformanceReview;
use App\Models\AppraisalIncrement;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('HR Management', description: 'Payroll, staff loans, performance appraisal, and HR analytics.', weight: 90)]
class AppraisalController extends Controller
{
    /**
     * List all KPI definitions.
     * 
     * @operationId appraisal.kpi.index
     */
    public function indexKpis(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $kpis = KpiDefinition::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $kpis,
        ]);
    }

    /**
     * Create a KPI definition.
     * 
     * @operationId appraisal.kpi.store
     */
    public function storeKpi(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'category' => ['required', 'string', 'max:50'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'measurement_unit' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $kpi = KpiDefinition::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'KPI definition created successfully.',
            'data' => $kpi,
        ], 201);
    }

    /**
     * Submit a self-assessment review.
     * 
     * @operationId appraisal.review.submit
     */
    public function submitReview(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'staff_id' => ['required', 'uuid'],
            'staff_type' => ['required', Rule::in(['teacher', 'employee'])],
            'review_period' => ['required', 'string', 'max:50'],
            'self_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $review = PerformanceReview::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'staff_id' => $validated['staff_id'],
                'staff_type' => $validated['staff_type'],
                'review_period' => $validated['review_period'],
            ],
            [
                'self_score' => $validated['self_score'],
                'status' => 'submitted',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Self-assessment submitted successfully.',
            'data' => $review,
        ]);
    }

    /**
     * Manager review and finalize score.
     * 
     * @operationId appraisal.review.finalize
     */
    public function finalizeReview(Request $request, PerformanceReview $review): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($review->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'manager_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'rating_band' => ['required', 'string', 'max:50'],
            'manager_comments' => ['nullable', 'string'],
        ]);

        $finalScore = ($review->self_score + $validated['manager_score']) / 2;

        $review->update([
            'manager_score' => $validated['manager_score'],
            'final_score' => $finalScore,
            'rating_band' => $validated['rating_band'],
            'manager_comments' => $validated['manager_comments'],
            'status' => 'finalized',
            'reviewer_id' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Performance review finalized successfully.',
            'data' => $review,
        ]);
    }

    /**
     * Grant salary increment based on review.
     * 
     * @operationId appraisal.increment.store
     */
    public function grantIncrement(Request $request, PerformanceReview $review): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($review->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'increment_amount' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ]);

        // Fetch current basic salary from staff model
        $staff = match ($review->staff_type) {
            'teacher' => \App\Models\Teacher::findOrFail($review->staff_id),
            'employee' => \App\Models\Employee::findOrFail($review->staff_id),
        };

        $previousBasic = $staff->basic_salary ?? 0;
        $newBasic = $previousBasic + $validated['increment_amount'];
        $percentage = $previousBasic > 0 ? ($validated['increment_amount'] / $previousBasic) * 100 : 0;

        $increment = AppraisalIncrement::create([
            'tenant_id' => $tenant->id,
            'performance_review_id' => $review->id,
            'staff_id' => $review->staff_id,
            'staff_type' => $review->staff_type,
            'previous_basic' => $previousBasic,
            'increment_amount' => $validated['increment_amount'],
            'new_basic' => $newBasic,
            'percentage_increase' => $percentage,
            'effective_date' => $validated['effective_date'],
            'created_by' => $request->user()->id,
        ]);

        // Update staff's basic salary
        $staff->update(['basic_salary' => $newBasic]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary increment applied successfully.',
            'data' => $increment,
        ]);
    }

    /**
     * List all reviews for the tenant.
     * 
     * @operationId appraisal.reviews.index
     */
    public function indexReviews(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $reviews = PerformanceReview::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $reviews,
        ]);
    }
}

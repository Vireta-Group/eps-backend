<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAddress;
use App\Models\TenantContact;
use App\Models\TenantSetting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('School Setup & Onboarding')]
class OnboardingWizardController extends Controller
{
    private const TOTAL_STEPS = 5;

    private const STEPS = [
        1 => 'school_basic_info',
        2 => 'academic_calendar',
        3 => 'class_section_subject',
        4 => 'teacher_student',
        5 => 'fee_notification',
    ];

    private function getTenant(Request $request): Tenant
    {
        return $request->user()->tenant;
    }

    private function stepRules(int $step): array
    {
        return match ($step) {
            1 => [
                'name_en' => ['required', 'string', 'max:200'],
                'name_bn' => ['required', 'string', 'max:200'],
                'school_type' => ['required', 'string', 'in:Primary,High School,College,Madrasa,Kindergarten'],
                'eiin_number' => ['nullable', 'string', 'max:20'],
                'board_affiliation' => ['nullable', 'string', 'max:100'],
                'mpo_status' => ['nullable', 'boolean'],
                'established_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
                'email' => ['nullable', 'email', 'max:200'],
                'phone_primary' => ['required', 'string', 'max:20'],
                'phone_secondary' => ['nullable', 'string', 'max:20'],
                'whatsapp_number' => ['nullable', 'string', 'max:20'],
                'website_url' => ['nullable', 'url', 'max:200'],
                'logo_url' => ['nullable', 'string', 'max:200'],
                'address' => ['nullable', 'array'],
                'address.division' => ['nullable', 'string', 'max:100'],
                'address.district' => ['nullable', 'string', 'max:100'],
                'address.upazila' => ['nullable', 'string', 'max:100'],
                'address.village_area' => ['nullable', 'string', 'max:500'],
                'address.google_map_url' => ['nullable', 'url', 'max:500'],
                'principal' => ['nullable', 'array'],
                'principal.name_en' => ['nullable', 'string', 'max:200'],
                'principal.name_bn' => ['nullable', 'string', 'max:200'],
                'principal.designation' => ['nullable', 'string', 'max:100'],
                'principal.mobile' => ['nullable', 'string', 'max:20'],
                'principal.email' => ['nullable', 'email', 'max:200'],
            ],
            2 => [
                'academic_year_start' => ['nullable', 'integer', 'min:1', 'max:12'],
                'default_language' => ['nullable', 'string', 'max:10'],
                'timezone' => ['nullable', 'string', 'timezone'],
                'date_format' => ['nullable', 'string', 'max:30'],
                'currency' => ['nullable', 'string', 'max:3'],
                'working_days' => ['sometimes', 'array'],
                'working_days.*' => ['string', 'in:sun,mon,tue,wed,thu,fri,sat'],
                'holidays' => ['nullable', 'array'],
                'holidays.*' => ['string', 'max:200'],
            ],
            3 => [
                'classes' => ['required', 'array', 'min:1'],
                'classes.*.name' => ['required', 'string', 'max:100'],
                'classes.*.sections' => ['nullable', 'array'],
                'classes.*.sections.*' => ['string', 'max:50'],
                'subjects' => ['nullable', 'array'],
                'subjects.*.name' => ['required_with:subjects', 'string', 'max:100'],
                'subjects.*.code' => ['nullable', 'string', 'max:50'],
                'subjects.*.type' => ['nullable', 'string', 'in:theory,practical,both'],
            ],
            4 => [
                'teachers' => ['nullable', 'array'],
                'teachers.*.name_en' => ['required_with:teachers', 'string', 'max:200'],
                'teachers.*.name_bn' => ['nullable', 'string', 'max:200'],
                'teachers.*.email' => ['nullable', 'email'],
                'teachers.*.phone' => ['nullable', 'string', 'max:20'],
                'students' => ['nullable', 'array'],
                'students.*.name_en' => ['required_with:students', 'string', 'max:200'],
                'students.*.name_bn' => ['nullable', 'string', 'max:200'],
                'students.*.class' => ['nullable', 'string', 'max:100'],
                'students.*.section' => ['nullable', 'string', 'max:50'],
            ],
            5 => [
                'fee_types' => ['required', 'array', 'min:1'],
                'fee_types.*.name' => ['required', 'string', 'max:100'],
                'fee_types.*.amount' => ['required', 'numeric', 'min:0'],
                'fee_types.*.frequency' => ['required', 'string', 'in:monthly,yearly,one_time'],
                'notifications' => ['nullable', 'array'],
                'notifications.sms' => ['nullable', 'boolean'],
                'notifications.email' => ['nullable', 'boolean'],
                'notifications.push' => ['nullable', 'boolean'],
                'notifications.whatsapp' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }

    public function status(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);

        $completedSteps = [];
        for ($i = 1; $i < $tenant->onboarding_step; $i++) {
            $completedSteps[] = $i;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'current_step' => $tenant->onboarding_step,
                'total_steps' => self::TOTAL_STEPS,
                'completed_steps' => $completedSteps,
                'step_names' => self::STEPS,
                'onboarding_done' => $tenant->onboarding_done,
            ],
        ]);
    }

    public function showStep(Request $request, int $step): JsonResponse
    {
        if (! isset(self::STEPS[$step])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid step number.',
            ], 422);
        }

        $tenant = $this->getTenant($request);

        $data = match ($step) {
            1 => array_merge(
                $tenant->only([
                    'name_en', 'name_bn', 'school_type', 'eiin_number',
                    'board_affiliation', 'mpo_status', 'established_year',
                    'email', 'phone_primary', 'phone_secondary',
                    'whatsapp_number', 'website_url', 'logo_url',
                ]),
                ['address' => optional(TenantAddress::where('tenant_id', $tenant->id)->first())->only([
                    'division', 'district', 'upazila', 'village_area', 'google_map_url',
                ])],
                ['principal' => optional(TenantContact::where('tenant_id', $tenant->id)->first())->only([
                    'name_en', 'name_bn', 'designation', 'mobile', 'email',
                ])]
            ),
            2 => array_merge(
                $tenant->only([
                    'academic_year_start', 'default_language', 'timezone',
                    'date_format', 'currency',
                ]),
                $this->getSettingData($tenant, 'step_2'),
            ),
            default => $this->getSettingData($tenant, 'step_'.$step),
        };

        return response()->json([
            'status' => 'success',
            'data' => [
                'step' => $step,
                'name' => self::STEPS[$step],
                'data' => $data,
            ],
        ]);
    }

    public function saveStep(Request $request, int $step): JsonResponse
    {
        if (! isset(self::STEPS[$step])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid step number.',
            ], 422);
        }

        $tenant = $this->getTenant($request);

        if ($tenant->onboarding_done) {
            return response()->json([
                'status' => 'error',
                'message' => 'Onboarding is already completed.',
            ], 422);
        }

        if ($step < $tenant->onboarding_step) {
            return $this->saveStepData($request, $tenant, $step);
        }

        if ($step > $tenant->onboarding_step) {
            return response()->json([
                'status' => 'error',
                'message' => "Please complete step {$tenant->onboarding_step} first.",
            ], 422);
        }

        return $this->saveStepData($request, $tenant, $step);
    }

    private function saveStepData(Request $request, Tenant $tenant, int $step): JsonResponse
    {
        $validated = $request->validate($this->stepRules($step));

        match ($step) {
            1 => $this->saveStep1($tenant, $validated),
            2 => $this->saveStep2($tenant, $validated),
            default => $this->saveSetting($tenant, 'step_'.$step, $validated),
        };

        if ($step === $tenant->onboarding_step && $step < self::TOTAL_STEPS) {
            $tenant->increment('onboarding_step');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Step '.$step.' saved successfully.',
            'data' => [
                'step' => $step,
                'next_step' => $step < self::TOTAL_STEPS ? $step + 1 : null,
                'onboarding_step' => $tenant->fresh()->onboarding_step,
            ],
        ]);
    }

    private function saveStep1(Tenant $tenant, array $data): void
    {
        $tenantFields = collect($data)->only([
            'name_en', 'name_bn', 'school_type', 'eiin_number',
            'board_affiliation', 'mpo_status', 'established_year',
            'email', 'phone_primary', 'phone_secondary',
            'whatsapp_number', 'website_url', 'logo_url',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($tenantFields)) {
            $tenant->update($tenantFields);
        }

        if (isset($data['address'])) {
            TenantAddress::updateOrCreate(
                ['tenant_id' => $tenant->id, 'address_type' => 'main'],
                array_merge(
                    collect($data['address'])->only([
                        'division', 'district', 'upazila', 'village_area', 'google_map_url',
                    ])->filter(fn ($v) => ! is_null($v))->toArray(),
                    ['is_primary' => true]
                )
            );
        }

        if (isset($data['principal'])) {
            TenantContact::updateOrCreate(
                ['tenant_id' => $tenant->id, 'contact_type' => 'principal'],
                array_merge(
                    collect($data['principal'])->only([
                        'name_en', 'name_bn', 'designation', 'mobile', 'email',
                    ])->filter(fn ($v) => ! is_null($v))->toArray(),
                    ['is_primary' => true]
                )
            );
        }
    }

    private function saveStep2(Tenant $tenant, array $data): void
    {
        $tenantFields = collect($data)->only([
            'academic_year_start', 'default_language', 'timezone',
            'date_format', 'currency',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($tenantFields)) {
            $tenant->update($tenantFields);
        }

        $settingData = collect($data)->only([
            'working_days', 'holidays',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($settingData)) {
            $this->saveSetting($tenant, 'step_2', $settingData);
        }
    }

    public function finish(Request $request): JsonResponse
    {
        $tenant = $this->getTenant($request);

        if ($tenant->onboarding_step < self::TOTAL_STEPS) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please complete all steps first.',
            ], 422);
        }

        $tenant->update([
            'onboarding_done' => true,
            'tenant_status' => 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Onboarding completed! Your school is now active.',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id,
                    'name_en' => $tenant->name_en,
                    'onboarding_done' => true,
                    'tenant_status' => 'active',
                ],
            ],
        ]);
    }

    private function getSettingData(Tenant $tenant, string $groupKey): array
    {
        $setting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('group_key', $groupKey)
            ->first();

        return $setting?->settings ?? [];
    }

    private function saveSetting(Tenant $tenant, string $groupKey, array $data): void
    {
        TenantSetting::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'group_key' => $groupKey,
            ],
            [
                'settings' => $data,
            ]
        );
    }
}

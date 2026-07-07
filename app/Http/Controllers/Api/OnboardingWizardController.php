<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAddress;
use App\Models\TenantBranding;
use App\Models\TenantContact;
use App\Models\TenantSetting;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('School Setup & Onboarding')]
class OnboardingWizardController extends Controller
{
    private const TOTAL_STEPS = 4;

    private const STEPS = [
        1 => 'school_basic_info',
        2 => 'academic_calendar',
        3 => 'address_principal',
        4 => 'notification_branding',
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
                'name_short' => ['nullable', 'string', 'max:50'],
                'school_type' => ['required', 'string', 'in:Primary,High School,College,Madrasa,Kindergarten'],
                'eiin_number' => ['nullable', 'string', 'max:20'],
                'board_affiliation' => ['nullable', 'string', 'max:100'],
                'mpo_status' => ['nullable', 'boolean'],
                'mpo_index' => ['nullable', 'string', 'max:50'],
                'established_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
                'tin_number' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:200'],
                'phone_primary' => ['required', 'string', 'max:20'],
                'phone_secondary' => ['nullable', 'string', 'max:20'],
                'whatsapp_number' => ['nullable', 'string', 'max:20'],
                'website_url' => ['nullable', 'url', 'max:200'],
                'logo_url' => ['nullable', 'string', 'max:200'],
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
            4 => [
                'notifications' => ['nullable', 'array'],
                'notifications.sms' => ['nullable', 'boolean'],
                'notifications.email' => ['nullable', 'boolean'],
                'notifications.push' => ['nullable', 'boolean'],
                'notifications.whatsapp' => ['nullable', 'boolean'],
                'primary_color' => ['nullable', 'string', 'max:7'],
                'secondary_color' => ['nullable', 'string', 'max:7'],
                'accent_color' => ['nullable', 'string', 'max:7'],
                'logo_primary_url' => ['nullable', 'string', 'max:200'],
                'logo_landscape_url' => ['nullable', 'string', 'max:200'],
                'logo_dark_url' => ['nullable', 'string', 'max:200'],
                'favicon_url' => ['nullable', 'string', 'max:200'],
                'font_family' => ['nullable', 'string', 'max:100'],
                'custom_css' => ['nullable', 'string'],
            ],
            default => [],
        };
    }

    private function stepFields(int $step): array
    {
        return match ($step) {
            1 => [
                'name_en' => ['type' => 'string', 'required' => true, 'max' => 200, 'label' => 'School name (English)'],
                'name_bn' => ['type' => 'string', 'required' => true, 'max' => 200, 'label' => 'School name (Bengali)'],
                'name_short' => ['type' => 'string', 'required' => false, 'max' => 50, 'label' => 'Short name'],
                'school_type' => ['type' => 'string', 'required' => true, 'enum' => ['Primary', 'High School', 'College', 'Madrasa', 'Kindergarten']],
                'eiin_number' => ['type' => 'string', 'required' => false, 'max' => 20],
                'board_affiliation' => ['type' => 'string', 'required' => false, 'max' => 100],
                'mpo_status' => ['type' => 'boolean', 'required' => false],
                'mpo_index' => ['type' => 'string', 'required' => false, 'max' => 50],
                'established_year' => ['type' => 'integer', 'required' => false, 'min' => 1800, 'max' => 2100],
                'tin_number' => ['type' => 'string', 'required' => false, 'max' => 30],
                'email' => ['type' => 'string', 'required' => false, 'format' => 'email', 'max' => 200],
                'phone_primary' => ['type' => 'string', 'required' => true, 'max' => 20],
                'phone_secondary' => ['type' => 'string', 'required' => false, 'max' => 20],
                'whatsapp_number' => ['type' => 'string', 'required' => false, 'max' => 20],
                'website_url' => ['type' => 'string', 'required' => false, 'format' => 'url', 'max' => 200],
                'logo_url' => ['type' => 'string', 'required' => false, 'max' => 200],
            ],
            2 => [
                'academic_year_start' => ['type' => 'integer', 'required' => false, 'min' => 1, 'max' => 12],
                'default_language' => ['type' => 'string', 'required' => false, 'max' => 10],
                'timezone' => ['type' => 'string', 'required' => false, 'format' => 'timezone'],
                'date_format' => ['type' => 'string', 'required' => false, 'max' => 30],
                'currency' => ['type' => 'string', 'required' => false, 'max' => 3],
                'working_days' => ['type' => 'array', 'required' => false, 'items' => ['type' => 'string', 'enum' => ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat']]],
                'holidays' => ['type' => 'array', 'required' => false, 'items' => ['type' => 'string']],
            ],
            3 => [
                'address.division' => ['type' => 'string', 'required' => false, 'max' => 100],
                'address.district' => ['type' => 'string', 'required' => false, 'max' => 100],
                'address.upazila' => ['type' => 'string', 'required' => false, 'max' => 100],
                'address.village_area' => ['type' => 'string', 'required' => false, 'max' => 500],
                'address.google_map_url' => ['type' => 'string', 'required' => false, 'format' => 'url'],
                'principal.name_en' => ['type' => 'string', 'required' => false, 'max' => 200],
                'principal.name_bn' => ['type' => 'string', 'required' => false, 'max' => 200],
                'principal.designation' => ['type' => 'string', 'required' => false, 'max' => 100],
                'principal.mobile' => ['type' => 'string', 'required' => false, 'max' => 20],
                'principal.email' => ['type' => 'string', 'required' => false, 'format' => 'email'],
            ],
            4 => [
                'notifications.sms' => ['type' => 'boolean', 'required' => false],
                'notifications.email' => ['type' => 'boolean', 'required' => false],
                'notifications.push' => ['type' => 'boolean', 'required' => false],
                'notifications.whatsapp' => ['type' => 'boolean', 'required' => false],
                'primary_color' => ['type' => 'string', 'required' => false, 'max' => 7],
                'secondary_color' => ['type' => 'string', 'required' => false, 'max' => 7],
                'accent_color' => ['type' => 'string', 'required' => false, 'max' => 7],
                'logo_primary_url' => ['type' => 'string', 'required' => false, 'max' => 200],
                'logo_landscape_url' => ['type' => 'string', 'required' => false, 'max' => 200],
                'logo_dark_url' => ['type' => 'string', 'required' => false, 'max' => 200],
                'favicon_url' => ['type' => 'string', 'required' => false, 'max' => 200],
                'font_family' => ['type' => 'string', 'required' => false, 'max' => 100],
                'custom_css' => ['type' => 'string', 'required' => false],
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

    /**
     * Get onboarding step data and field schema.
     *
     * Returns saved data (if any) plus the expected field definitions for the step.
     * Frontend can use the `fields` key to dynamically render form inputs.
     *
     * The response includes:
     * - `data` — previously saved values for the step
     * - `fields` — object with field names as keys, each containing `type`, `required`, `label`, and validation constraints
     *
     * @operationId getOnboardingStep
     */
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
            1 => $tenant->only([
                'name_en', 'name_bn', 'name_short', 'school_type', 'eiin_number',
                'board_affiliation', 'mpo_status', 'mpo_index', 'established_year',
                'tin_number', 'email', 'phone_primary', 'phone_secondary',
                'whatsapp_number', 'website_url', 'logo_url',
            ]),
            2 => array_merge(
                $tenant->only([
                    'academic_year_start', 'default_language', 'timezone',
                    'date_format', 'currency',
                ]),
                $this->getSettingData($tenant, 'step_2'),
            ),
            3 => array_merge(
                $this->getSettingData($tenant, 'step_3'),
                ['address' => optional(TenantAddress::where('tenant_id', $tenant->id)
                    ->where('address_type', 'main')->first())->only([
                        'division', 'district', 'upazila', 'village_area', 'google_map_url',
                    ])],
                ['principal' => optional(TenantContact::where('tenant_id', $tenant->id)
                    ->where('contact_type', 'principal')->first())->only([
                        'name_en', 'name_bn', 'designation', 'mobile', 'email',
                    ])],
            ),
            4 => array_merge(
                $this->getSettingData($tenant, 'step_4'),
                optional(TenantBranding::where('tenant_id', $tenant->id)->first())->only([
                    'primary_color', 'secondary_color', 'accent_color',
                    'logo_primary_url', 'logo_landscape_url', 'logo_dark_url',
                    'favicon_url', 'font_family', 'custom_css',
                ]) ?? [],
            ),
            default => [],
        };

        return response()->json([
            'status' => 'success',
            'data' => [
                'step' => $step,
                'name' => self::STEPS[$step],
                'data' => $data,
                'fields' => $this->stepFields($step),
            ],
        ]);
    }

    /**
     * Save onboarding step 1 — School Basic Info.
     *
     * **Required:** name_en, name_bn, school_type, phone_primary
     * **Optional:** name_short, eiin_number, board_affiliation, mpo_status, mpo_index,
     * established_year, tin_number, email, phone_secondary, whatsapp_number, website_url, logo_url
     *
     * @operationId saveOnboardingStep1
     */
    public function saveStep1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:200'],
            'name_bn' => ['required', 'string', 'max:200'],
            'name_short' => ['nullable', 'string', 'max:50'],
            'school_type' => ['required', 'string', 'in:Primary,High School,College,Madrasa,Kindergarten'],
            'eiin_number' => ['nullable', 'string', 'max:20'],
            'board_affiliation' => ['nullable', 'string', 'max:100'],
            'mpo_status' => ['nullable', 'boolean'],
            'mpo_index' => ['nullable', 'string', 'max:50'],
            'established_year' => ['nullable', 'integer', 'min:1800', 'max:2100'],
            'tin_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone_primary' => ['required', 'string', 'max:20'],
            'phone_secondary' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'website_url' => ['nullable', 'url', 'max:200'],
            'logo_url' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->processStepSave($request, 1, $validated);
    }

    /**
     * Save onboarding step 2 — Academic Calendar.
     *
     * **Optional:** academic_year_start (month 1-12), default_language, timezone,
     * date_format, currency, working_days (array of day codes), holidays (array of strings)
     *
     * @operationId saveOnboardingStep2
     */
    public function saveStep2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_start' => ['nullable', 'integer', 'min:1', 'max:12'],
            'default_language' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'date_format' => ['nullable', 'string', 'max:30'],
            'currency' => ['nullable', 'string', 'max:3'],
            'working_days' => ['sometimes', 'array'],
            'working_days.*' => ['string', 'in:sun,mon,tue,wed,thu,fri,sat'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['string', 'max:200'],
        ]);

        return $this->processStepSave($request, 2, $validated);
    }

    /**
     * Save onboarding step 3 — Address & Principal Info.
     *
     * **Optional:** address (division, district, upazila, village_area, google_map_url),
     * principal (name_en, name_bn, designation, mobile, email)
     *
     * @operationId saveOnboardingStep3
     */
    public function saveStep3(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
        ]);

        return $this->processStepSave($request, 3, $validated);
    }

    /**
     * Save onboarding step 4 — Notification & Branding.
     *
     * **Optional:** notifications (sms, email, push, whatsapp),
     * primary_color, secondary_color, accent_color, logo_primary_url, logo_landscape_url,
     * logo_dark_url, favicon_url, font_family, custom_css
     *
     * @operationId saveOnboardingStep4
     */
    public function saveStep4(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notifications' => ['nullable', 'array'],
            'notifications.sms' => ['nullable', 'boolean'],
            'notifications.email' => ['nullable', 'boolean'],
            'notifications.push' => ['nullable', 'boolean'],
            'notifications.whatsapp' => ['nullable', 'boolean'],
            'primary_color' => ['nullable', 'string', 'max:7'],
            'secondary_color' => ['nullable', 'string', 'max:7'],
            'accent_color' => ['nullable', 'string', 'max:7'],
            'logo_primary_url' => ['nullable', 'string', 'max:200'],
            'logo_landscape_url' => ['nullable', 'string', 'max:200'],
            'logo_dark_url' => ['nullable', 'string', 'max:200'],
            'favicon_url' => ['nullable', 'string', 'max:200'],
            'font_family' => ['nullable', 'string', 'max:100'],
            'custom_css' => ['nullable', 'string'],
        ]);

        return $this->processStepSave($request, 4, $validated);
    }

    /**
     * Save onboarding step (generic — backward compatible).
     *
     * Delegates to the individual step method based on the step number.
     * Prefer using the specific endpoints (`POST /api/setup/step/1`, etc.).
     *
     * @operationId saveOnboardingStep
     */
    public function saveStep(Request $request, int $step): JsonResponse
    {
        $methodName = 'saveStep'.$step;
        if (method_exists($this, $methodName)) {
            return $this->$methodName($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid step number.',
        ], 422);
    }

    private function processStepSave(Request $request, int $step, array $validated): JsonResponse
    {
        $tenant = $this->getTenant($request);

        if ($tenant->onboarding_done) {
            return response()->json([
                'status' => 'error',
                'message' => 'Onboarding is already completed.',
            ], 422);
        }

        if ($step > $tenant->onboarding_step) {
            return response()->json([
                'status' => 'error',
                'message' => "Please complete step {$tenant->onboarding_step} first.",
            ], 422);
        }

        $this->persistStepData($tenant, $step, $validated);

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

    private function persistStepData(Tenant $tenant, int $step, array $data): void
    {
        match ($step) {
            1 => $this->persistStep1($tenant, $data),
            2 => $this->persistStep2($tenant, $data),
            3 => $this->persistStep3($tenant, $data),
            4 => $this->persistStep4($tenant, $data),
        };
    }

    private function persistStep1(Tenant $tenant, array $data): void
    {
        $tenantFields = collect($data)->only([
            'name_en', 'name_bn', 'name_short', 'school_type', 'eiin_number',
            'board_affiliation', 'mpo_status', 'mpo_index', 'established_year',
            'tin_number', 'email', 'phone_primary', 'phone_secondary',
            'whatsapp_number', 'website_url', 'logo_url',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($tenantFields)) {
            $tenant->update($tenantFields);
        }
    }

    private function persistStep2(Tenant $tenant, array $data): void
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

    private function persistStep3(Tenant $tenant, array $data): void
    {
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

    private function persistStep4(Tenant $tenant, array $data): void
    {
        $settingData = collect($data)->only([
            'notifications',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($settingData)) {
            $this->saveSetting($tenant, 'step_4', $settingData);
        }

        $brandingData = collect($data)->only([
            'primary_color', 'secondary_color', 'accent_color',
            'logo_primary_url', 'logo_landscape_url', 'logo_dark_url',
            'favicon_url', 'font_family', 'custom_css',
        ])->filter(fn ($v) => ! is_null($v))->toArray();

        if (! empty($brandingData)) {
            TenantBranding::updateOrCreate(
                ['tenant_id' => $tenant->id],
                $brandingData,
            );
        }
    }

    /**
     * Complete onboarding.
     *
     * Marks onboarding as done and activates the tenant.
     * All steps must be completed first.
     *
     * @operationId completeOnboarding
     */
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

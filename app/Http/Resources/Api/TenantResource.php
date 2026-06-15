<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'slug' => $this->slug,
            'school_type' => $this->school_type,
            'tenant_status' => $this->tenant_status,
            'onboarding_step' => $this->onboarding_step,
            'onboarding_done' => $this->onboarding_done,
            'setup_progress_percentage' => $this->onboarding_done
                ? 100
                : (int) round((($this->onboarding_step - 1) / 5) * 100),
            'logo_url' => $this->logo_url,
            'timezone' => $this->timezone,
            'default_language' => $this->default_language,
        ];
    }
}

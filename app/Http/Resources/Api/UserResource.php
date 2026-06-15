<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_bn' => $this->name_bn,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_type' => $this->user_type,
            'user_status' => $this->user_status,
            'profile_photo_url' => $this->profile_photo_url,
            'tenant_id' => $this->tenant_id,
        ];
    }
}

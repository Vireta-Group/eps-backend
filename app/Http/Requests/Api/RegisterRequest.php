<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:200'],
            'name_bn' => ['required', 'string', 'max:200'],
            'school_type' => ['required', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'admin_name_en' => ['required', 'string', 'max:200'],
            'admin_name_bn' => ['required', 'string', 'max:200'],
            'admin_password' => ['required', 'string', Password::defaults()],
        ];
    }
}

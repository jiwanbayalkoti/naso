<?php

namespace App\Http\Requests\Rider;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creatingWithUser = $this->filled('email') && $this->filled('password');

        return [
            'user_id' => [Rule::requiredIf(! $creatingWithUser), 'nullable', 'exists:users,id', 'unique:riders,user_id'],
            'name' => [Rule::requiredIf($creatingWithUser), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf($creatingWithUser), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [Rule::requiredIf($creatingWithUser), 'nullable', 'string', 'min:8'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'is_online' => ['prohibited'],
            'is_available' => ['sometimes', 'boolean'],
            'current_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'current_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}

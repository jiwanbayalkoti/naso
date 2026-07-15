<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'shop_registration_enabled' => ['sometimes', 'boolean'],
            'rider_registration_enabled' => ['sometimes', 'boolean'],
            'dashboard_refresh_interval' => ['required', 'integer', 'min:5', 'max:300'],
            'delivery_offer_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'delivery_base_fee' => ['required', 'numeric', 'min:0'],
            'delivery_fee_per_km' => ['required', 'numeric', 'min:0'],
            'delivery_min_fee' => ['required', 'numeric', 'min:0'],
            'platform_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}

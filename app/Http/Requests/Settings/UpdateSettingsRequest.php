<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'delivery_pricing' => ['sometimes', 'array'],
            'delivery_pricing.mode' => ['required_with:delivery_pricing', 'in:zone_slabs,linear'],
            'delivery_pricing.valley' => ['required_with:delivery_pricing', 'array'],
            'delivery_pricing.valley.lat' => ['required_with:delivery_pricing', 'numeric', 'between:-90,90'],
            'delivery_pricing.valley.lng' => ['required_with:delivery_pricing', 'numeric', 'between:-180,180'],
            'delivery_pricing.valley.radius_km' => ['required_with:delivery_pricing', 'numeric', 'min:0', 'max:500'],
            'delivery_pricing.inside_valley' => ['required_with:delivery_pricing', 'array', 'min:1'],
            'delivery_pricing.inside_valley.*.from_km' => ['required_with:delivery_pricing', 'numeric', 'min:0'],
            'delivery_pricing.inside_valley.*.to_km' => ['nullable', 'numeric', 'min:0'],
            'delivery_pricing.inside_valley.*.fee' => ['required_with:delivery_pricing', 'numeric', 'min:0'],
            'delivery_pricing.outside_valley' => ['required_with:delivery_pricing', 'array', 'min:1'],
            'delivery_pricing.outside_valley.*.label' => ['nullable', 'string', 'max:50'],
            'delivery_pricing.outside_valley.*.from_km' => ['required_with:delivery_pricing', 'numeric', 'min:0'],
            'delivery_pricing.outside_valley.*.to_km' => ['nullable', 'numeric', 'min:0'],
            'delivery_pricing.outside_valley.*.fee' => ['required_with:delivery_pricing', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['shop_registration_enabled', 'rider_registration_enabled'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateSlabRanges($validator, 'delivery_pricing.inside_valley');
            $this->validateSlabRanges($validator, 'delivery_pricing.outside_valley');
        });
    }

    protected function validateSlabRanges(Validator $validator, string $key): void
    {
        $slabs = data_get($this->all(), $key);
        if (! is_array($slabs)) {
            return;
        }

        foreach ($slabs as $index => $slab) {
            if (! is_array($slab)) {
                continue;
            }
            $from = isset($slab['from_km']) ? (float) $slab['from_km'] : null;
            $to = array_key_exists('to_km', $slab) && $slab['to_km'] !== null && $slab['to_km'] !== ''
                ? (float) $slab['to_km']
                : null;

            if ($from !== null && $to !== null && $to <= $from) {
                $validator->errors()->add("{$key}.{$index}.to_km", 'To km must be greater than from km (or empty for open-ended).');
            }
        }
    }
}

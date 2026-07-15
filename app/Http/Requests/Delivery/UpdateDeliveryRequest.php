<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user?->hasRole('shop') && $user->shop) {
            $this->merge([
                'shop_id' => $user->shop->id,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['sometimes', 'exists:shops,id'],
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'string', 'max:50'],
            'pickup_address' => ['sometimes', 'string'],
            'delivery_address' => ['sometimes', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['nullable', 'string'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'card', 'online', 'wallet'])],
            'payment_status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
        ];
    }
}

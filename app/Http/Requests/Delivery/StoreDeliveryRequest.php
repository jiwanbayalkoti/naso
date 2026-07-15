<?php

namespace App\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryRequest extends FormRequest
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
        $user = $this->user();

        return [
            'shop_id' => [
                $user?->hasRole('shop') ? 'sometimes' : 'required',
                'exists:shops,id',
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'pickup_address' => ['required', 'string'],
            'delivery_address' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['nullable', 'string'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'cod_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'cod', 'card', 'online', 'wallet'])],
            'payment_status' => ['sometimes', 'string', Rule::in(['pending', 'paid', 'failed', 'refunded'])],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}

<?php

namespace App\Http\Requests\Delivery;

use App\Helpers\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryStatusRequest extends FormRequest
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
        return [
            'status' => ['required', 'string', Rule::in(DeliveryStatus::all())],
            'notes' => ['nullable', 'string'],
        ];
    }
}

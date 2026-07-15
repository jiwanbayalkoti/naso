<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopRequest extends FormRequest
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
        $creatingWithOwner = $this->filled('owner_email');

        return [
            'user_id' => [Rule::requiredIf(! $creatingWithOwner), 'nullable', 'exists:users,id'],
            'owner_name' => [Rule::requiredIf($creatingWithOwner), 'nullable', 'string', 'max:255'],
            'owner_email' => [Rule::requiredIf($creatingWithOwner), 'nullable', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_password' => [Rule::requiredIf($creatingWithOwner), 'nullable', 'string', 'min:8'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:shops,slug'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}

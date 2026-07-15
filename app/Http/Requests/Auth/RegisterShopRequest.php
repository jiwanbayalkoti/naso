<?php

namespace App\Http\Requests\Auth;

use App\Helpers\VerificationDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RegisterShopRequest extends FormRequest
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
        $documentRule = ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)];
        $optionalDocumentRule = ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)];

        return [
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'documents.pan' => $documentRule,
            'documents.citizenship' => $documentRule,
            'documents.nid' => $optionalDocumentRule,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'documents.pan' => VerificationDocumentType::label(VerificationDocumentType::PAN),
            'documents.citizenship' => VerificationDocumentType::label(VerificationDocumentType::CITIZENSHIP),
            'documents.nid' => VerificationDocumentType::label(VerificationDocumentType::NID),
        ];
    }
}

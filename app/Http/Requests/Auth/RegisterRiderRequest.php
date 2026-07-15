<?php

namespace App\Http\Requests\Auth;

use App\Helpers\VerificationDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RegisterRiderRequest extends FormRequest
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
        $requiredDocumentRule = ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)];
        $optionalDocumentRule = ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'license_number' => ['required', 'string', 'max:100'],
            'documents.license' => $requiredDocumentRule,
            'documents.blue_book' => $requiredDocumentRule,
            'documents.citizenship' => $requiredDocumentRule,
            'documents.nid' => $optionalDocumentRule,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'documents.license' => VerificationDocumentType::label(VerificationDocumentType::LICENSE),
            'documents.blue_book' => VerificationDocumentType::label(VerificationDocumentType::BLUE_BOOK),
            'documents.citizenship' => VerificationDocumentType::label(VerificationDocumentType::CITIZENSHIP),
            'documents.nid' => VerificationDocumentType::label(VerificationDocumentType::NID),
        ];
    }
}

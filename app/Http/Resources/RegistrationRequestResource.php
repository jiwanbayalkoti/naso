<?php

namespace App\Http\Resources;

use App\Helpers\ApprovalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->resource['uuid'],
            'type' => $this->resource['type'],
            'type_label' => $this->resource['type_label'],
            'name' => $this->resource['name'],
            'email' => $this->resource['email'],
            'phone' => $this->resource['phone'],
            'approval_status' => $this->resource['approval_status'],
            'approval_status_label' => ApprovalStatus::label($this->resource['approval_status']),
            'documents_count' => $this->resource['documents_count'],
            'submitted_at' => $this->resource['submitted_at'],
        ];
    }
}

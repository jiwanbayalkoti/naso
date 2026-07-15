<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo' => $this->logo,
            'is_active' => $this->is_active,
            'approval_status' => $this->approval_status,
            'approval_status_label' => $this->approval_status ? \App\Helpers\ApprovalStatus::label($this->approval_status) : null,
            'rejection_reason' => $this->rejection_reason,
            'description' => $this->description,
            'pan_number' => $this->pan_number,
            'nid_number' => $this->nid_number,
            'balance' => (float) ($this->balance ?? 0),
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'owner_name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'deliveries_count' => $this->deliveries_count ?? 0,
            'user' => new UserResource($this->whenLoaded('user')),
            'documents' => VerificationDocumentResource::collection($this->whenLoaded('verificationDocuments')),
            'created_at' => $this->created_at?->format('M d, Y'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

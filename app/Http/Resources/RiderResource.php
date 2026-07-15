<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RiderResource extends JsonResource
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
            'vehicle_type' => $this->vehicle_type,
            'vehicle_number' => $this->vehicle_number,
            'license_number' => $this->license_number,
            'pan_number' => $this->pan_number,
            'nid_number' => $this->nid_number,
            'approval_status' => $this->approval_status,
            'approval_status_label' => $this->approval_status ? \App\Helpers\ApprovalStatus::label($this->approval_status) : null,
            'rejection_reason' => $this->rejection_reason,
            'is_online' => $this->isPresentlyOnline(),
            'wants_online' => (bool) $this->is_online,
            'is_present' => $this->isPresentlyOnline(),
            'is_available' => $this->is_available,
            'current_latitude' => $this->current_latitude,
            'current_longitude' => $this->current_longitude,
            'location_updated_at' => $this->location_updated_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'rating' => $this->rating,
            'total_deliveries' => $this->total_deliveries,
            'balance' => (float) ($this->balance ?? 0),
            'bank_name' => $this->bank_name,
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => $this->bank_account_number,
            'name' => $this->relationLoaded('user') ? $this->user?->name : null,
            'email' => $this->relationLoaded('user') ? $this->user?->email : null,
            'phone' => $this->relationLoaded('user') ? $this->user?->phone : null,
            'status' => $this->isPresentlyOnline() ? 'Online' : 'Offline',
            'active_deliveries' => $this->when(isset($this->active_deliveries_count), $this->active_deliveries_count),
            'user' => new UserResource($this->whenLoaded('user')),
            'documents' => VerificationDocumentResource::collection($this->whenLoaded('verificationDocuments')),
            'created_at' => $this->created_at?->format('M d, Y'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

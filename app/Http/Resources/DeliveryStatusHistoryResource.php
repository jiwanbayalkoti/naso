<?php

namespace App\Http\Resources;

use App\Helpers\DeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => DeliveryStatus::labels()[$this->status] ?? $this->status,
            'previous_status' => $this->previous_status,
            'previous_status_label' => $this->previous_status
                ? (DeliveryStatus::labels()[$this->previous_status] ?? $this->previous_status)
                : null,
            'notes' => $this->notes,
            'changed_by' => new UserResource($this->whenLoaded('changedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

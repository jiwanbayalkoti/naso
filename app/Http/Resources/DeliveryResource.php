<?php

namespace App\Http\Resources;

use App\Helpers\DeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'tracking_number' => $this->tracking_number,
            'shop_id' => $this->shop_id,
            'rider_id' => $this->rider_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'pickup_address' => $this->pickup_address,
            'delivery_address' => $this->delivery_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
            'status_label' => DeliveryStatus::labels()[$this->status] ?? $this->status,
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'is_offer_expired' => $this->isOfferExpired(),
            'is_offer_active' => $this->isOfferActive(),
            'priority' => $this->priority,
            'notes' => $this->notes,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'delivery_fee' => $this->delivery_fee,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'shop_name' => $this->relationLoaded('shop') ? $this->shop?->name : null,
            'rider' => new RiderResource($this->whenLoaded('rider')),
            'rider_name' => $this->relationLoaded('rider')
                ? ($this->rider?->relationLoaded('user') ? $this->rider?->user?->name : null) ?? 'Unassigned'
                : null,
            'status_histories' => DeliveryStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'id' => $this->uuid,
            'created_at' => $this->created_at?->format('M d, Y H:i'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

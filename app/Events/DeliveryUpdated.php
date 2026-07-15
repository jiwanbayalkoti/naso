<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public string $eventType = 'status_updated'
    ) {
        $this->delivery->loadMissing(['shop', 'rider.user']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('deliveries'),
            new Channel('delivery.'.$this->delivery->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => $this->eventType,
            'uuid' => $this->delivery->uuid,
            'tracking_number' => $this->delivery->tracking_number,
            'status' => $this->delivery->status,
            'shop_name' => $this->delivery->shop?->name,
            'rider_name' => $this->delivery->rider?->user?->name,
            'updated_at' => $this->delivery->updated_at?->toIso8601String(),
        ];
    }
}

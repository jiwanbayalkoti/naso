<?php

namespace App\Notifications;

use App\Models\Delivery;
use Illuminate\Notifications\Notification;

class DeliveryCompletedNotification extends Notification
{
    public function __construct(
        protected Delivery $delivery
    ) {
        $this->delivery->loadMissing(['shop', 'rider.user']);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $riderName = $this->delivery->rider?->user?->name ?? 'Rider';

        return [
            'type' => 'delivery_completed',
            'delivery_uuid' => $this->delivery->uuid,
            'tracking_number' => $this->delivery->tracking_number,
            'customer_name' => $this->delivery->customer_name,
            'rider_name' => $riderName,
            'message' => 'Delivery '.$this->delivery->tracking_number.' has been completed by '.$riderName.'.',
            'url' => route('deliveries.show', $this->delivery->uuid),
        ];
    }
}

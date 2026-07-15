<?php

namespace App\Notifications;

use App\Models\Delivery;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeliveryCompletedNotification extends Notification
{
    public function __construct(
        protected Delivery $delivery,
        protected string $recipientRole = 'shop'
    ) {
        $this->delivery->loadMissing(['shop.user', 'rider.user']);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        // In-app database notifications for platform users only.
        if ($this->recipientRole !== 'customer') {
            $channels[] = 'database';
        }

        $email = $notifiable->routeNotificationFor('mail', $this)
            ?? (property_exists($notifiable, 'email') ? $notifiable->email : null);
        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        $phone = $notifiable->routeNotificationFor('sms', $this)
            ?? (property_exists($notifiable, 'phone') ? $notifiable->phone : null);
        if (is_string($phone) && preg_replace('/\D+/', '', $phone) !== '') {
            $channels[] = 'sms';
        }

        return $channels !== [] ? $channels : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amounts = $this->amounts();
        $tracking = $this->delivery->tracking_number;
        $mail = (new MailMessage)
            ->subject($this->mailSubject())
            ->greeting($this->greeting($notifiable))
            ->line($this->summaryLine());

        foreach ($this->amountLines($amounts) as $line) {
            $mail->line($line);
        }

        $mail->line('Tracking: '.$tracking);

        if ($this->recipientRole !== 'customer') {
            $mail->action('View delivery', url('/deliveries/'.$this->delivery->uuid));
        }

        return $mail->salutation('— '.config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $a = $this->amounts();
        $t = $this->delivery->tracking_number;

        return match ($this->recipientRole) {
            'rider' => sprintf(
                'NASO: Delivery %s completed. Fee Rs %.2f, commission Rs %.2f, your earning Rs %.2f.',
                $t,
                $a['delivery_fee'],
                $a['platform_commission'],
                $a['rider_earning']
            ),
            'admin' => sprintf(
                'NASO: %s done. COD Rs %.2f, fee Rs %.2f, commission Rs %.2f, rider Rs %.2f.',
                $t,
                $a['cod_amount'],
                $a['delivery_fee'],
                $a['platform_commission'],
                $a['rider_earning']
            ),
            'customer' => sprintf(
                'NASO: Your delivery %s is completed. COD Rs %.2f, delivery fee Rs %.2f. Thank you!',
                $t,
                $a['cod_amount'],
                $a['delivery_fee']
            ),
            default => sprintf(
                'NASO: Delivery %s completed. COD credited Rs %.2f, fee deducted Rs %.2f, net Rs %.2f.',
                $t,
                $a['cod_amount'],
                $a['delivery_fee'],
                $a['shop_net']
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $amounts = $this->amounts();
        $riderName = $this->delivery->rider?->user?->name ?? 'Rider';

        return [
            'type' => 'delivery_completed',
            'recipient_role' => $this->recipientRole,
            'delivery_uuid' => $this->delivery->uuid,
            'tracking_number' => $this->delivery->tracking_number,
            'customer_name' => $this->delivery->customer_name,
            'rider_name' => $riderName,
            'cod_amount' => $amounts['cod_amount'],
            'delivery_fee' => $amounts['delivery_fee'],
            'rider_earning' => $amounts['rider_earning'],
            'platform_commission' => $amounts['platform_commission'],
            'shop_net' => $amounts['shop_net'],
            'message' => trim($this->summaryLine().' '.$this->shortAmountText()),
            'url' => url('/deliveries/'.$this->delivery->uuid),
        ];
    }

    /**
     * @return array{cod_amount: float, delivery_fee: float, rider_earning: float, platform_commission: float, shop_net: float}
     */
    public function amounts(): array
    {
        $fee = (float) ($this->delivery->delivery_fee ?? 0);
        $cod = (float) ($this->delivery->cod_amount ?? 0);
        $earning = (float) ($this->delivery->rider_earning ?? 0);
        $commission = (float) ($this->delivery->platform_commission ?? 0);

        return [
            'cod_amount' => $cod,
            'delivery_fee' => $fee,
            'rider_earning' => $earning,
            'platform_commission' => $commission,
            'shop_net' => round($cod - $fee, 2),
        ];
    }

    public function recipientRole(): string
    {
        return $this->recipientRole;
    }

    protected function shortAmountText(): string
    {
        $a = $this->amounts();

        return match ($this->recipientRole) {
            'rider' => 'Earning Rs '.number_format($a['rider_earning'], 2).'.',
            'admin' => 'Commission Rs '.number_format($a['platform_commission'], 2).'.',
            default => 'Net Rs '.number_format($a['shop_net'], 2).'.',
        };
    }

    protected function mailSubject(): string
    {
        return match ($this->recipientRole) {
            'rider' => 'Ride completed — earning summary',
            'admin' => 'Delivery completed — settlement summary',
            'customer' => 'Your delivery is completed',
            default => 'Delivery completed — wallet update',
        };
    }

    protected function greeting(object $notifiable): string
    {
        $name = property_exists($notifiable, 'name') ? $notifiable->name : null;

        return 'Hello'.($name ? ' '.$name : '').'!';
    }

    protected function summaryLine(): string
    {
        $t = $this->delivery->tracking_number;

        return match ($this->recipientRole) {
            'rider' => "Delivery {$t} is completed. Your ride earning has been credited.",
            'admin' => "Delivery {$t} is completed. Platform settlement amounts below.",
            'customer' => "Your order {$t} has been delivered successfully.",
            default => "Delivery {$t} is completed. Your wallet has been updated.",
        };
    }

    /**
     * @param  array{cod_amount: float, delivery_fee: float, rider_earning: float, platform_commission: float, shop_net: float}  $a
     * @return list<string>
     */
    protected function amountLines(array $a): array
    {
        return match ($this->recipientRole) {
            'rider' => [
                'Delivery fee: Rs '.number_format($a['delivery_fee'], 2),
                'Platform commission: Rs '.number_format($a['platform_commission'], 2),
                'Your earning: Rs '.number_format($a['rider_earning'], 2),
            ],
            'admin' => [
                'COD collected: Rs '.number_format($a['cod_amount'], 2),
                'Delivery fee: Rs '.number_format($a['delivery_fee'], 2),
                'Platform commission: Rs '.number_format($a['platform_commission'], 2),
                'Rider earning: Rs '.number_format($a['rider_earning'], 2),
            ],
            'customer' => [
                'COD amount: Rs '.number_format($a['cod_amount'], 2),
                'Delivery fee: Rs '.number_format($a['delivery_fee'], 2),
            ],
            default => [
                'COD credited: Rs '.number_format($a['cod_amount'], 2),
                'Delivery fee deducted: Rs '.number_format($a['delivery_fee'], 2),
                'Net to your wallet: Rs '.number_format($a['shop_net'], 2),
            ],
        };
    }
}

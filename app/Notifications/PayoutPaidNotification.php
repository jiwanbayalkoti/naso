<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaidNotification extends Notification
{
    public function __construct(
        protected Payout $payout,
        protected bool $wasPartial = false,
        protected ?float $remainderAmount = null
    ) {
        $this->payout->loadMissing(['payable.user', 'processedBy']);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ctx = $this->context();
        $mail = (new MailMessage)
            ->subject('Payout '.($this->wasPartial ? 'partially ' : '').'paid — Rs '.$ctx['amount_fmt'])
            ->greeting('Hello'.(property_exists($notifiable, 'name') && $notifiable->name ? ' '.$notifiable->name : '').'!')
            ->line($ctx['message'])
            ->line('Paid amount: Rs '.$ctx['amount_fmt']);

        if ($ctx['reference']) {
            $mail->line('Reference: '.$ctx['reference']);
        }

        if ($this->wasPartial && $this->remainderAmount !== null && $this->remainderAmount > 0) {
            $mail->line('Remaining pending request: Rs '.number_format($this->remainderAmount, 2));
        }

        $walletUrl = $ctx['type'] === 'shop' ? url('/wallet/shop') : url('/wallet/rider');

        return $mail
            ->action('View wallet', $walletUrl)
            ->salutation('— '.config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $ctx = $this->context();
        $msg = sprintf(
            'NASO: Payout of Rs %s %spaid to your bank%s.',
            $ctx['amount_fmt'],
            $this->wasPartial ? 'partially ' : '',
            $ctx['reference'] ? ' (ref '.$ctx['reference'].')' : ''
        );

        if ($this->wasPartial && $this->remainderAmount !== null && $this->remainderAmount > 0) {
            $msg .= ' Remaining Rs '.number_format($this->remainderAmount, 2).' still pending.';
        }

        return $msg;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $ctx = $this->context();
        $walletUrl = $ctx['type'] === 'shop' ? url('/wallet/shop') : url('/wallet/rider');

        return [
            'type' => 'payout_paid',
            'message' => $ctx['message'],
            'payout_uuid' => $this->payout->uuid,
            'payable_type' => $ctx['type'],
            'payable_uuid' => $ctx['payable_uuid'],
            'amount' => $ctx['amount'],
            'was_partial' => $this->wasPartial,
            'remainder_amount' => $this->remainderAmount,
            'reference' => $ctx['reference'],
            'url' => $walletUrl,
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     payable_uuid: ?string,
     *     amount: float,
     *     amount_fmt: string,
     *     reference: ?string,
     *     message: string
     * }
     */
    protected function context(): array
    {
        $payable = $this->payout->payable;
        $type = $payable instanceof Shop ? 'shop' : 'rider';
        $amount = (float) $this->payout->amount;
        $amountFmt = number_format($amount, 2);

        return [
            'type' => $type,
            'payable_uuid' => $payable?->uuid,
            'amount' => $amount,
            'amount_fmt' => $amountFmt,
            'reference' => $this->payout->reference,
            'message' => $this->wasPartial
                ? "A partial payout of Rs {$amountFmt} has been transferred to your bank."
                : "Your payout of Rs {$amountFmt} has been transferred to your bank.",
        ];
    }
}

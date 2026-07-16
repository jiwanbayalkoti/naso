<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Models\Shop;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestedNotification extends Notification
{
    public function __construct(
        protected Payout $payout
    ) {
        $this->payout->loadMissing(['payable.user', 'requestedBy']);
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

        return (new MailMessage)
            ->subject('Payout request — Rs '.$ctx['amount_fmt'])
            ->greeting('Hello'.(property_exists($notifiable, 'name') && $notifiable->name ? ' '.$notifiable->name : '').'!')
            ->line($ctx['message'])
            ->line('Account: '.$ctx['name'].' ('.strtoupper($ctx['type']).')')
            ->line('Requested amount: Rs '.$ctx['amount_fmt'])
            ->line('Bank: '.($ctx['bank_name'] ?: '—'))
            ->line('Account name: '.($ctx['bank_account_name'] ?: '—'))
            ->line('Account number: '.($ctx['bank_account_number'] ?: '—'))
            ->action('Open payouts', url('/payouts'))
            ->salutation('— '.config('app.name'));
    }

    public function toSms(object $notifiable): string
    {
        $ctx = $this->context();

        return sprintf(
            'NASO: %s requested Rs %s payout. Bank: %s %s. Open Payouts to pay.',
            $ctx['name'],
            $ctx['amount_fmt'],
            $ctx['bank_name'] ?: 'N/A',
            $ctx['bank_account_number'] ?: ''
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $ctx = $this->context();

        return [
            'type' => 'payout_requested',
            'message' => $ctx['message'],
            'payout_uuid' => $this->payout->uuid,
            'payable_type' => $ctx['type'],
            'payable_uuid' => $ctx['payable_uuid'],
            'payable_name' => $ctx['name'],
            'amount' => $ctx['amount'],
            'bank_name' => $ctx['bank_name'],
            'bank_account_name' => $ctx['bank_account_name'],
            'bank_account_number' => $ctx['bank_account_number'],
            'url' => url('/payouts'),
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     name: string,
     *     payable_uuid: ?string,
     *     amount: float,
     *     amount_fmt: string,
     *     bank_name: ?string,
     *     bank_account_name: ?string,
     *     bank_account_number: ?string,
     *     message: string
     * }
     */
    protected function context(): array
    {
        $payable = $this->payout->payable;
        $type = $payable instanceof Shop ? 'shop' : 'rider';
        $name = $payable instanceof Shop
            ? ($payable->name ?? 'Shop')
            : ($payable?->user?->name ?? 'Rider');
        $amount = (float) $this->payout->amount;
        $amountFmt = number_format($amount, 2);
        $mode = $this->isFullRequest($payable, $amount) ? 'full' : 'partial';

        return [
            'type' => $type,
            'name' => $name,
            'payable_uuid' => $payable?->uuid,
            'amount' => $amount,
            'amount_fmt' => $amountFmt,
            'bank_name' => $payable?->bank_name,
            'bank_account_name' => $payable?->bank_account_name,
            'bank_account_number' => $payable?->bank_account_number,
            'message' => sprintf(
                '%s requested a %s payout of Rs %s.',
                $name,
                $mode,
                $amountFmt
            ),
        ];
    }

    protected function isFullRequest(?object $payable, float $amount): bool
    {
        if (! $payable) {
            return false;
        }

        return abs($amount - (float) ($payable->balance ?? 0)) < 0.009;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(?string $to, string $message): bool
    {
        $to = $this->normalizePhone($to);
        if ($to === null || $to === '' || trim($message) === '') {
            return false;
        }

        $driver = config('services.sms.driver', 'log');

        try {
            return match ($driver) {
                'sparrow' => $this->sendSparrow($to, $message),
                'http' => $this->sendHttp($to, $message),
                default => $this->sendLog($to, $message),
            };
        } catch (\Throwable $e) {
            Log::warning('SMS send failed', [
                'to' => $to,
                'driver' => $driver,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function sendLog(string $to, string $message): bool
    {
        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);

        return true;
    }

    protected function sendHttp(string $to, string $message): bool
    {
        $url = config('services.sms.http_url');
        if (! $url) {
            return $this->sendLog($to, $message);
        }

        $payload = [
            'to' => $to,
            'message' => $message,
            'from' => config('services.sms.from'),
        ];

        $response = Http::timeout(15)
            ->withHeaders(array_filter([
                'Authorization' => config('services.sms.http_token')
                    ? 'Bearer '.config('services.sms.http_token')
                    : null,
                'Accept' => 'application/json',
            ]))
            ->post($url, array_merge($payload, config('services.sms.http_extra', [])));

        if (! $response->successful()) {
            Log::warning('SMS HTTP failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Sparrow SMS (Nepal) — https://sparrowsms.com
     */
    protected function sendSparrow(string $to, string $message): bool
    {
        $token = config('services.sms.sparrow_token');
        $from = config('services.sms.from', 'Demo');
        if (! $token) {
            return $this->sendLog($to, $message);
        }

        $response = Http::asForm()->timeout(15)->post('https://api.sparrowsms.com/v2/sms/', [
            'token' => $token,
            'from' => $from,
            'to' => $to,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('Sparrow SMS failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : null;
    }
}

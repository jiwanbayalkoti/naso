<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 50);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($notification) => $this->formatNotification($notification));

        return $this->success($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()
            ->notifications()
            ->where('id', $notification)
            ->first();

        if (! $record) {
            return $this->error('Notification not found.', 404);
        }

        $record->markAsRead();

        return $this->success(null, 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success(null, 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatNotification(object $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? 'general',
            'message' => $data['message'] ?? '',
            'tracking_number' => $data['tracking_number'] ?? null,
            'delivery_uuid' => $data['delivery_uuid'] ?? null,
            'payout_uuid' => $data['payout_uuid'] ?? null,
            'payable_type' => $data['payable_type'] ?? null,
            'payable_uuid' => $data['payable_uuid'] ?? null,
            'payable_name' => $data['payable_name'] ?? null,
            'amount' => $data['amount'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'was_partial' => $data['was_partial'] ?? null,
            'url' => $data['url'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}

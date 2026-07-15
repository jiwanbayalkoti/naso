<?php

namespace App\Repositories;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        protected Delivery $delivery,
        protected Shop $shop,
        protected Rider $rider,
        protected User $user
    ) {}

    public function getStats(?int $shopId = null, ?int $riderId = null): array
    {
        $deliveryQuery = $this->scopedDeliveryQuery($shopId, $riderId);

        return [
            'total_deliveries' => (clone $deliveryQuery)->count(),
            'pending_deliveries' => (clone $deliveryQuery)->where('status', DeliveryStatus::PENDING)->count(),
            'active_deliveries' => (clone $deliveryQuery)->whereNotIn('status', [
                DeliveryStatus::COMPLETED,
                DeliveryStatus::CANCELLED,
            ])->count(),
            'completed_deliveries' => (clone $deliveryQuery)->where('status', DeliveryStatus::COMPLETED)->count(),
            'cancelled_deliveries' => (clone $deliveryQuery)->where('status', DeliveryStatus::CANCELLED)->count(),
            'assigned_deliveries' => $riderId
                ? (clone $deliveryQuery)->where('status', DeliveryStatus::ASSIGNED)->count()
                : 0,
            'total_shops' => $shopId ? 1 : $this->shop->newQuery()->count(),
            'total_riders' => $riderId ? 1 : $this->rider->newQuery()->count(),
            'online_riders' => $this->rider->newQuery()->presentOnline()->count(),
            'total_users' => $this->user->newQuery()->count(),
            'total_revenue' => (float) (clone $deliveryQuery)
                ->where('status', DeliveryStatus::COMPLETED)
                ->sum('delivery_fee'),
        ];
    }

    public function getTrends(int $days = 30, ?int $shopId = null, ?int $riderId = null): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        return $this->scopedDeliveryQuery($shopId, $riderId)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();
    }

    public function getStatusChart(?int $shopId = null, ?int $riderId = null): array
    {
        return $this->scopedDeliveryQuery($shopId, $riderId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    public function getLatestDeliveries(int $limit = 10, ?int $shopId = null, ?int $riderId = null): Collection
    {
        return $this->scopedDeliveryQuery($shopId, $riderId)
            ->with(['shop', 'rider.user'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getPendingDeliveries(int $limit = 10, ?int $shopId = null): Collection
    {
        $query = $this->scopedDeliveryQuery($shopId, null)
            ->where('status', DeliveryStatus::PENDING)
            ->with(['shop'])
            ->latest()
            ->limit($limit);

        return $query->get();
    }

    public function getOnlineRiders(): Collection
    {
        return $this->rider->newQuery()
            ->presentOnline()
            ->with('user')
            ->get();
    }

    protected function scopedDeliveryQuery(?int $shopId, ?int $riderId)
    {
        $query = $this->delivery->newQuery();

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        if ($riderId !== null) {
            $query->where('rider_id', $riderId);
        }

        return $query;
    }
}

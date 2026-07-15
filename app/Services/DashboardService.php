<?php

namespace App\Services;

use App\Http\Resources\DeliveryResource;
use App\Http\Resources\RiderResource;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardService extends BaseService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository
    ) {}

    public function getStats(): array
    {
        [$shopId, $riderId] = $this->resolveScope();

        return $this->dashboardRepository->getStats($shopId, $riderId);
    }

    public function getTrends(int $days = 30): array
    {
        [$shopId, $riderId] = $this->resolveScope();

        $raw = $this->dashboardRepository->getTrends($days, $shopId, $riderId);
        $labels = [];
        $values = [];

        $startDate = now()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $startDate->copy()->addDays($i)->format('M d');
            $values[] = (int) ($raw[$date] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function getStatusChart(): array
    {
        [$shopId, $riderId] = $this->resolveScope();

        $raw = $this->dashboardRepository->getStatusChart($shopId, $riderId);
        $labels = [];
        $values = [];
        $colors = [
            'pending' => '#d97706',
            'assigned' => '#7c3aed',
            'accepted' => '#0891b2',
            'picked_up' => '#2563eb',
            'on_the_way' => '#4f46e5',
            'delivered' => '#16a34a',
            'completed' => '#15803d',
            'cancelled' => '#dc2626',
        ];
        $chartColors = [];

        foreach ($raw as $status => $total) {
            $labels[] = \App\Helpers\DeliveryStatus::labels()[$status] ?? ucfirst(str_replace('_', ' ', $status));
            $values[] = (int) $total;
            $chartColors[] = $colors[$status] ?? '#64748b';
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $chartColors,
        ];
    }

    public function getLatestDeliveries(int $limit = 10): array
    {
        [$shopId, $riderId] = $this->resolveScope();

        $deliveries = $this->dashboardRepository->getLatestDeliveries($limit, $shopId, $riderId);

        return DeliveryResource::collection($deliveries)->resolve();
    }

    public function getPendingDeliveries(int $limit = 10): array
    {
        [$shopId] = $this->resolveScope();

        $deliveries = $this->dashboardRepository->getPendingDeliveries($limit, $shopId);

        return DeliveryResource::collection($deliveries)->resolve();
    }

    public function getOnlineRiders(): array
    {
        $riders = $this->dashboardRepository->getOnlineRiders();

        return RiderResource::collection($riders)->resolve();
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    protected function resolveScope(): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return [null, null];
        }

        $user->loadMissing(['roles', 'shop', 'rider']);

        if ($user->hasRole('super_admin')) {
            return [null, null];
        }

        if ($user->hasRole('shop')) {
            return [$user->shop?->id, null];
        }

        if ($user->hasRole('rider')) {
            return [null, $user->rider?->id];
        }

        return [null, null];
    }
}

<?php

namespace App\Services;

use App\Helpers\DeliveryStatus;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\RiderResource;
use App\Models\Delivery;
use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Support\Facades\Auth;

class DashboardService extends BaseService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboardRepository,
        protected WalletService $walletService
    ) {}

    public function getStats(): array
    {
        [$shopId, $riderId] = $this->resolveScope();

        return array_merge(
            $this->dashboardRepository->getStats($shopId, $riderId),
            $this->earningStats()
        );
    }

    /**
     * Wallet / payout summary for the signed-in role.
     *
     * @return array<string, float|int|string|null>
     */
    protected function earningStats(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return [
                'my_earning' => 0,
                'available_for_payout' => 0,
                'pending_payout_total' => 0,
                'paid_payout_total' => 0,
                'earning_label' => 'My Earning',
            ];
        }

        $user->loadMissing(['roles', 'shop', 'rider']);

        if ($user->hasRole('shop') && $user->shop) {
            $shop = $user->shop;
            $pending = $this->pendingPaidTotals(Shop::class, $shop->id);

            return [
                'my_earning' => (float) $shop->balance,
                'available_for_payout' => $this->walletService->availableForPayout($shop),
                'pending_payout_total' => $pending['pending'],
                'paid_payout_total' => $pending['paid'],
                'earning_label' => 'My Balance',
                'payment_history_url' => url('/payouts'),
            ];
        }

        if ($user->hasRole('rider') && $user->rider) {
            $rider = $user->rider;
            $pending = $this->pendingPaidTotals(Rider::class, $rider->id);

            return [
                'my_earning' => (float) $rider->balance,
                'available_for_payout' => $this->walletService->availableForPayout($rider),
                'pending_payout_total' => $pending['pending'],
                'paid_payout_total' => $pending['paid'],
                'earning_label' => 'My Earning',
                'payment_history_url' => url('/payouts'),
            ];
        }

        // Super admin: platform commission earned + payout pipeline totals.
        $commission = (float) Delivery::query()
            ->where('status', DeliveryStatus::COMPLETED)
            ->sum('platform_commission');
        $pendingAll = (float) Payout::query()->where('status', Payout::STATUS_PENDING)->sum('amount');
        $paidAll = (float) Payout::query()->where('status', Payout::STATUS_PAID)->sum('amount');

        return [
            'my_earning' => $commission,
            'available_for_payout' => $pendingAll,
            'pending_payout_total' => $pendingAll,
            'paid_payout_total' => $paidAll,
            'earning_label' => 'My Earning',
            'payment_history_url' => url('/payouts'),
        ];
    }

    /**
     * @return array{pending: float, paid: float}
     */
    protected function pendingPaidTotals(string $payableType, int $payableId): array
    {
        return [
            'pending' => (float) Payout::query()
                ->where('payable_type', $payableType)
                ->where('payable_id', $payableId)
                ->where('status', Payout::STATUS_PENDING)
                ->sum('amount'),
            'paid' => (float) Payout::query()
                ->where('payable_type', $payableType)
                ->where('payable_id', $payableId)
                ->where('status', Payout::STATUS_PAID)
                ->sum('amount'),
        ];
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

<?php

namespace App\Repositories;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeliveryRepository extends BaseRepository implements DeliveryRepositoryInterface
{
    protected array $searchableColumns = [
        'tracking_number',
        'customer_name',
        'customer_phone',
        'pickup_address',
        'delivery_address',
        'status',
    ];

    protected array $sortableColumns = [
        'tracking_number',
        'status',
        'priority',
        'delivery_fee',
        'assigned_at',
        'delivered_at',
        'created_at',
    ];

    protected function resolveModel(): Model
    {
        return new Delivery;
    }

    public function datatableQuery(array $filters = []): Builder
    {
        $query = parent::datatableQuery($filters)->with(['shop', 'rider.user']);

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (! empty($filters['rider_id'])) {
            $query->where('rider_id', $filters['rider_id']);
        }

        return $query;
    }

    public function findByTrackingNumber(string $trackingNumber): ?Delivery
    {
        return $this->newQuery()
            ->where('tracking_number', $trackingNumber)
            ->with(['shop', 'rider.user', 'statusHistories.changedBy'])
            ->first();
    }

    public function getByShopId(int $shopId, array $filters = []): Collection
    {
        $filters['shop_id'] = $shopId;

        return $this->datatableQuery($filters)->get();
    }

    public function getByRiderId(int $riderId, array $filters = []): Collection
    {
        $filters['rider_id'] = $riderId;

        return $this->datatableQuery($filters)->get();
    }

    public function getPending(int $limit = 10): Collection
    {
        return $this->newQuery()
            ->where('status', DeliveryStatus::PENDING)
            ->with(['shop'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function getUnclaimedPending(int $limit = 20): Collection
    {
        return $this->newQuery()
            ->where('status', DeliveryStatus::PENDING)
            ->whereNull('rider_id')
            ->where(function (Builder $query) {
                $query->whereNull('offer_expires_at')
                    ->orWhere('offer_expires_at', '>', now());
            })
            ->with(['shop'])
            ->oldest()
            ->limit($limit)
            ->get();
    }

    public function findForUpdateByUuid(string $uuid): ?Delivery
    {
        return $this->newQuery()
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first();
    }

    public function getLatest(int $limit = 10): Collection
    {
        return $this->newQuery()
            ->with(['shop', 'rider.user'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function countByStatus(): array
    {
        return $this->newQuery()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    public function getTrends(int $days = 30): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();

        return $this->newQuery()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();
    }

    public function generateTrackingNumber(): string
    {
        do {
            $number = sprintf(
                'NASO-%s-%05d',
                now()->format('Ymd'),
                random_int(1, 99999)
            );
        } while ($this->newQuery()->where('tracking_number', $number)->exists());

        return $number;
    }
}

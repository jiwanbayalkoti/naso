<?php

namespace App\Repositories\Contracts;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Collection;

interface DeliveryRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a delivery by tracking number.
     */
    public function findByTrackingNumber(string $trackingNumber): ?Delivery;

    /**
     * Get deliveries for a shop.
     */
    public function getByShopId(int $shopId, array $filters = []): Collection;

    /**
     * Get deliveries assigned to a rider.
     */
    public function getByRiderId(int $riderId, array $filters = []): Collection;

    /**
     * Get pending deliveries.
     */
    public function getPending(int $limit = 10): Collection;

    /**
     * Get latest deliveries.
     */
    public function getLatest(int $limit = 10): Collection;

    /**
     * Count deliveries by status.
     */
    public function countByStatus(): array;

    /**
     * Get delivery trends for chart data.
     */
    public function getTrends(int $days = 30): array;

    /**
     * Generate a unique tracking number.
     */
    public function generateTrackingNumber(): string;

    /**
     * Get pending deliveries waiting for rider acceptance.
     */
    public function getUnclaimedPending(int $limit = 20): Collection;

    /**
     * Find a delivery by UUID with row lock for update.
     */
    public function findForUpdateByUuid(string $uuid): ?Delivery;
}

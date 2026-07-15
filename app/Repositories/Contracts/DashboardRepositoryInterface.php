<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface DashboardRepositoryInterface
{
    /**
     * Get dashboard statistics.
     */
    public function getStats(?int $shopId = null, ?int $riderId = null): array;

    /**
     * Get delivery trends.
     */
    public function getTrends(int $days = 30, ?int $shopId = null, ?int $riderId = null): array;

    /**
     * Get delivery counts grouped by status.
     */
    public function getStatusChart(?int $shopId = null, ?int $riderId = null): array;

    /**
     * Get latest deliveries.
     */
    public function getLatestDeliveries(int $limit = 10, ?int $shopId = null, ?int $riderId = null): Collection;

    /**
     * Get pending deliveries.
     */
    public function getPendingDeliveries(int $limit = 10, ?int $shopId = null): Collection;

    /**
     * Get online riders.
     */
    public function getOnlineRiders(): Collection;
}

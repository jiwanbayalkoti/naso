<?php

namespace App\Repositories\Contracts;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Collection;

interface RiderRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a rider by user ID.
     */
    public function findByUserId(int $userId): ?Rider;

    /**
     * Get online and available riders.
     */
    public function getOnlineAvailable(): Collection;

    /**
     * Get online riders.
     */
    public function getOnline(): Collection;

    /**
     * Get riders available for delivery assignment dropdowns.
     */
    public function getAssignable(): Collection;
}

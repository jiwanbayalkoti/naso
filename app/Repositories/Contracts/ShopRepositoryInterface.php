<?php

namespace App\Repositories\Contracts;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;

interface ShopRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Find a shop by user ID.
     */
    public function findByUserId(int $userId): ?Shop;

    /**
     * Get active shops.
     */
    public function getActive(): Collection;

    /**
     * @return array<int, string>
     */
    public function getDistinctCities(): array;
}

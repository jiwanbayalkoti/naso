<?php

namespace App\Repositories;

use App\Models\Shop;
use App\Repositories\Contracts\ShopRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ShopRepository extends BaseRepository implements ShopRepositoryInterface
{
    protected array $searchableColumns = ['name', 'email', 'phone', 'city', 'slug'];

    protected array $sortableColumns = ['name', 'city', 'is_active', 'created_at'];

    protected function resolveModel(): Model
    {
        return new Shop;
    }

    public function datatableQuery(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        return parent::datatableQuery($filters)
            ->with('user')
            ->withCount('deliveries');
    }

    public function findByUserId(int $userId): ?Shop
    {
        return $this->newQuery()->where('user_id', $userId)->first();
    }

    public function getActive(): Collection
    {
        return $this->newQuery()->where('is_active', true)->get();
    }

    public function getDistinctCities(): array
    {
        return $this->newQuery()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }
}

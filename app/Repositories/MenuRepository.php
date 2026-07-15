<?php

namespace App\Repositories;

use App\Models\Menu;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class MenuRepository extends BaseRepository implements MenuRepositoryInterface
{
    protected array $searchableColumns = ['title', 'route_name', 'permission'];

    protected array $sortableColumns = ['title', 'route_name', 'sort_order', 'is_active', 'created_at'];

    protected function resolveModel(): Model
    {
        return new Menu;
    }

    public function datatableQuery(array $filters = []): Builder
    {
        $query = parent::datatableQuery($filters)->with('parent');

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query;
    }

    public function getSidebarTree(): Collection
    {
        return $this->newQuery()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function parentOptions(?int $excludeId = null): Collection
    {
        $query = $this->newQuery()
            ->whereNull('parent_id')
            ->orderBy('sort_order');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get(['id', 'uuid', 'title']);
    }
}

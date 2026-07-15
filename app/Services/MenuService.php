<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\User;
use App\Repositories\Contracts\MenuRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Route;

class MenuService extends BaseService
{
    public function __construct(
        protected MenuRepositoryInterface $menuRepository,
        protected AuditLogService $auditLogService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->menuRepository->datatable($filters);
    }

    public function findByUuid(string $uuid): ?Menu
    {
        return $this->menuRepository->findByUuid($uuid);
    }

    public function parentOptions(?string $excludeUuid = null): Collection
    {
        $excludeId = null;

        if ($excludeUuid) {
            $excludeId = $this->menuRepository->findByUuid($excludeUuid)?->id;
        }

        return $this->menuRepository->parentOptions($excludeId);
    }

    /**
     * @return array<int, string>
     */
    public function availableRouteNames(): array
    {
        return collect(Route::getRoutes())
            ->filter(function ($route) {
                $name = $route->getName();

                if (! $name || ! in_array('GET', $route->methods(), true)) {
                    return false;
                }

                if (str_starts_with($name, 'api.') || str_starts_with($name, 'sanctum.')) {
                    return false;
                }

                $allowed = [
                    'dashboard',
                    'deliveries.',
                    'shops.',
                    'riders.',
                    'users.',
                    'registration-requests.',
                    'menus.',
                    'activity-logs.',
                    'audit-logs.',
                    'profile.',
                    'settings.',
                ];

                foreach ($allowed as $prefix) {
                    if ($name === rtrim($prefix, '.') || str_starts_with($name, $prefix)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn ($route) => $route->getName())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function availablePermissions(): array
    {
        return \Spatie\Permission\Models\Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function getSidebarForUser(User $user): SupportCollection
    {
        return $this->menuRepository->getSidebarTree()
            ->filter(fn (Menu $menu) => $this->userCanSeeMenu($user, $menu))
            ->values();
    }

    public function create(array $data, ?int $actorId = null): Menu
    {
        return $this->transaction(function () use ($data, $actorId) {
            $menu = $this->menuRepository->create($this->prepareData($data, $actorId));
            $this->auditLogService->log($actorId, $menu, 'created', null, $menu->toArray());

            return $menu->load('parent');
        });
    }

    public function update(string $uuid, array $data, ?int $actorId = null): Menu
    {
        return $this->transaction(function () use ($uuid, $data, $actorId) {
            $menu = $this->menuRepository->findByUuid($uuid);

            if (! $menu) {
                abort(404, 'Menu not found.');
            }

            $oldValues = $menu->toArray();
            $menu = $this->menuRepository->update($menu->id, $this->prepareData($data, $actorId, false));

            $this->auditLogService->log($actorId, $menu, 'updated', $oldValues, $menu->toArray());

            return $menu->load('parent');
        });
    }

    public function delete(string $uuid, ?int $actorId = null): bool
    {
        return $this->transaction(function () use ($uuid, $actorId) {
            $menu = $this->menuRepository->findByUuid($uuid);

            if (! $menu) {
                abort(404, 'Menu not found.');
            }

            $oldValues = $menu->toArray();
            $deleted = $this->menuRepository->delete($menu->id);

            $this->auditLogService->log($actorId, $menu, 'deleted', $oldValues, null);

            return $deleted;
        });
    }

    public function export(array $filters = []): Collection
    {
        return $this->menuRepository->datatableQuery($filters)->get();
    }

    protected function userCanSeeMenu(User $user, Menu $menu): bool
    {
        if (! $menu->permission) {
            return true;
        }

        return $user->hasRole('super_admin') || $user->can($menu->permission);
    }

    /**
     * @return array<string, mixed>
     */
    protected function prepareData(array $data, ?int $actorId = null, bool $isCreate = true): array
    {
        $payload = Arr::only($data, (new Menu)->getFillable());

        $payload['parent_id'] = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $payload['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $payload['is_active'] = (bool) ($data['is_active'] ?? true);
        $payload['permission'] = $data['permission'] ?? null;
        $payload['route_pattern'] = $data['route_pattern'] ?? null;
        $payload['url'] = $data['url'] ?? null;
        $payload['icon'] = $data['icon'] ?? null;
        $payload['route_name'] = $data['route_name'] ?? null;

        if ($isCreate) {
            $payload['created_by'] = $actorId;
        } else {
            $payload['updated_by'] = $actorId;
        }

        return $payload;
    }
}

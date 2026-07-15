<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Models\Shop;
use App\Repositories\Contracts\ShopRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ShopService extends BaseService
{
    public function __construct(
        protected ShopRepositoryInterface $shopRepository,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->shopRepository->datatable($filters);
    }

    public function all(): Collection
    {
        return $this->shopRepository->all();
    }

    public function findByUuid(string $uuid): ?Shop
    {
        return $this->shopRepository->findByUuid($uuid);
    }

    public function getActive(): Collection
    {
        return $this->shopRepository->getActive();
    }

    public function create(array $data, ?int $userId = null): Shop
    {
        return $this->transaction(function () use ($data, $userId) {
            if (empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);
            }

            $shop = $this->shopRepository->create($data);

            $this->auditLogService->log($userId, $shop, 'created', null, $shop->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::SHOP_CREATED,
                'Shop created: '.$shop->name,
                $shop
            );

            return $shop->fresh(['user']);
        });
    }

    public function update(string $uuid, array $data, ?int $userId = null): Shop
    {
        return $this->transaction(function () use ($uuid, $data, $userId) {
            $shop = $this->shopRepository->findByUuid($uuid);

            if (! $shop) {
                abort(404, 'Shop not found.');
            }

            $oldValues = $shop->toArray();
            $shop = $this->shopRepository->update($shop->id, Arr::only($data, $shop->getFillable()));

            $this->auditLogService->log($userId, $shop, 'updated', $oldValues, $shop->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::SHOP_UPDATED,
                'Shop updated: '.$shop->name,
                $shop
            );

            return $shop->load('user');
        });
    }

    public function delete(string $uuid, ?int $userId = null): bool
    {
        return $this->transaction(function () use ($uuid, $userId) {
            $shop = $this->shopRepository->findByUuid($uuid);

            if (! $shop) {
                abort(404, 'Shop not found.');
            }

            $oldValues = $shop->toArray();
            $deleted = $this->shopRepository->delete($shop->id);

            $this->auditLogService->log($userId, $shop, 'deleted', $oldValues, null);

            return $deleted;
        });
    }

    public function export(array $filters = []): Collection
    {
        return $this->shopRepository->datatableQuery($filters)->get();
    }

    /**
     * @return array<int, string>
     */
    public function getDistinctCities(): array
    {
        return $this->shopRepository->getDistinctCities();
    }
}

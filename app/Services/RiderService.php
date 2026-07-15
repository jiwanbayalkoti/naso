<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Helpers\DeliveryStatus;
use App\Models\Rider;
use App\Repositories\Contracts\RiderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class RiderService extends BaseService
{
    public function __construct(
        protected RiderRepositoryInterface $riderRepository,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->riderRepository->datatable($filters);
    }

    public function findByUuid(string $uuid): ?Rider
    {
        return $this->riderRepository->findByUuid($uuid);
    }

    public function create(array $data, ?int $userId = null): Rider
    {
        return $this->transaction(function () use ($data, $userId) {
            unset($data['is_online']);
            $data['is_online'] = false;
            $data['last_seen_at'] = null;

            $rider = $this->riderRepository->create($data);

            $this->auditLogService->log($userId, $rider, 'created', null, $rider->toArray());

            return $rider->load('user');
        });
    }

    public function update(string $uuid, array $data, ?int $userId = null): Rider
    {
        return $this->transaction(function () use ($uuid, $data, $userId) {
            $rider = $this->riderRepository->findByUuid($uuid);

            if (! $rider) {
                abort(404, 'Rider not found.');
            }

            unset($data['is_online'], $data['last_seen_at']);

            $oldValues = $rider->toArray();
            $rider = $this->riderRepository->update($rider->id, Arr::only($data, $rider->getFillable()));

            $this->auditLogService->log($userId, $rider, 'updated', $oldValues, $rider->toArray());

            return $rider->load('user');
        });
    }

    public function delete(string $uuid, ?int $userId = null): bool
    {
        return $this->transaction(function () use ($uuid, $userId) {
            $rider = $this->riderRepository->findByUuid($uuid);

            if (! $rider) {
                abort(404, 'Rider not found.');
            }

            $oldValues = $rider->toArray();
            $deleted = $this->riderRepository->delete($rider->id);

            $this->auditLogService->log($userId, $rider, 'deleted', $oldValues, null);

            return $deleted;
        });
    }

    public function toggleOnline(string $uuid, ?int $userId = null): Rider
    {
        return $this->transaction(function () use ($uuid, $userId) {
            $rider = $this->riderRepository->findByUuid($uuid);

            if (! $rider) {
                abort(404, 'Rider not found.');
            }

            $oldValues = $rider->toArray();
            $isOnline = ! $rider->is_online;

            $payload = [
                'is_online' => $isOnline,
                'is_available' => $isOnline ? true : false,
                'last_seen_at' => $isOnline ? now() : null,
            ];

            $rider = $this->riderRepository->update($rider->id, $payload);

            $this->auditLogService->log($userId, $rider, 'updated', $oldValues, $rider->toArray());
            $this->activityLogService->log(
                $userId,
                $isOnline ? ActivityType::RIDER_ONLINE : ActivityType::RIDER_OFFLINE,
                $isOnline ? 'Rider went online.' : 'Rider went offline.',
                $rider
            );

            return $rider->load('user');
        });
    }

    public function heartbeat(string $uuid, ?int $userId = null): Rider
    {
        $rider = $this->riderRepository->findByUuid($uuid);

        if (! $rider) {
            abort(404, 'Rider not found.');
        }

        if (! $rider->is_online) {
            return $rider->load('user');
        }

        $rider->touchLastSeen();

        return $rider->fresh(['user']);
    }

    public function clearPresenceByUserId(int $userId): void
    {
        $rider = $this->riderRepository->findByUserId($userId);

        if (! $rider || $rider->last_seen_at === null) {
            return;
        }

        $oldValues = $rider->toArray();
        $rider->clearPresence();

        $this->auditLogService->log($userId, $rider, 'updated', $oldValues, $rider->fresh()?->toArray());
        $this->activityLogService->log(
            $userId,
            ActivityType::RIDER_OFFLINE,
            'Rider presence cleared (logout / away). Online preference kept.',
            $rider
        );
    }

    public function restorePresenceOnLogin(int $userId): ?Rider
    {
        $rider = $this->riderRepository->findByUserId($userId);

        if (! $rider) {
            return null;
        }

        if (! $rider->restorePresenceIfPreferred()) {
            return $rider->load('user');
        }

        $this->activityLogService->log(
            $userId,
            ActivityType::RIDER_ONLINE,
            'Rider presence restored on login (preferred Online).',
            $rider
        );

        return $rider->fresh(['user']);
    }

    /** @deprecated use clearPresenceByUserId — kept for older call sites */
    public function markOfflineByUserId(int $userId): void
    {
        $this->clearPresenceByUserId($userId);
    }

    public function export(array $filters = []): Collection
    {
        return $this->riderRepository->datatableQuery($filters)->get();
    }

    public function getAssignable(): Collection
    {
        return $this->riderRepository->getAssignable();
    }

    /**
     * Live GPS points for the rider map (admin: all with coords; shop: online + on their active deliveries).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLiveMapLocations(?\App\Models\User $user = null): array
    {
        $user = $user ?: auth()->user();

        $query = Rider::query()
            ->with('user')
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->whereHas('user', fn ($q) => $q->where('is_active', true));

        if ($user?->hasRole('shop')) {
            $shopId = $user->shop?->id;
            if (! $shopId) {
                return [];
            }

            $activeStatuses = [
                DeliveryStatus::ASSIGNED,
                DeliveryStatus::ACCEPTED,
                DeliveryStatus::PICKED_UP,
                DeliveryStatus::ON_THE_WAY,
                DeliveryStatus::DELIVERED,
            ];

            $assignedIds = \App\Models\Delivery::query()
                ->where('shop_id', $shopId)
                ->whereIn('status', $activeStatuses)
                ->whereNotNull('rider_id')
                ->pluck('rider_id')
                ->unique()
                ->filter()
                ->values()
                ->all();

            $query->where(function ($builder) use ($assignedIds) {
                $builder->presentOnline();
                if ($assignedIds !== []) {
                    $builder->orWhereIn('id', $assignedIds);
                }
            });
        } elseif (! $user?->hasRole('super_admin') && ! $user?->can('riders.view')) {
            return [];
        }

        return $query
            ->orderByDesc('location_updated_at')
            ->get()
            ->map(function (Rider $rider) {
                return [
                    'uuid' => $rider->uuid,
                    'id' => $rider->id,
                    'name' => $rider->user?->name ?? ('Rider #'.$rider->id),
                    'phone' => $rider->user?->phone,
                    'is_online' => $rider->isPresentlyOnline(),
                    'is_available' => (bool) $rider->is_available && $rider->isPresentlyOnline(),
                    'latitude' => (float) $rider->current_latitude,
                    'longitude' => (float) $rider->current_longitude,
                    'location_updated_at' => $rider->location_updated_at?->toIso8601String(),
                    'vehicle_type' => $rider->vehicle_type,
                    'vehicle_number' => $rider->vehicle_number,
                    'status' => $rider->isPresentlyOnline() ? 'Online' : 'Offline',
                ];
            })
            ->values()
            ->all();
    }

    public function updateLocation(string $uuid, float $latitude, float $longitude, ?int $userId = null): Rider
    {
        return $this->transaction(function () use ($uuid, $latitude, $longitude, $userId) {
            $rider = $this->riderRepository->findByUuid($uuid);

            if (! $rider) {
                abort(404, 'Rider not found.');
            }

            $oldValues = $rider->toArray();

            $rider = $this->riderRepository->update($rider->id, [
                'current_latitude' => $latitude,
                'current_longitude' => $longitude,
                'location_updated_at' => now(),
                'last_seen_at' => now(),
            ]);

            $this->auditLogService->log($userId, $rider, 'updated', $oldValues, $rider->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::RIDER_LOCATION_UPDATED,
                'Rider location updated.',
                $rider,
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]
            );

            return $rider->load('user');
        });
    }
}

<?php

namespace App\Services;

use App\Events\DeliveryUpdated;
use App\Helpers\ActivityType;
use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Rider;
use App\Notifications\DeliveryCompletedNotification;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\RiderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class DeliveryService extends BaseService
{
    public function __construct(
        protected DeliveryRepositoryInterface $deliveryRepository,
        protected RiderRepositoryInterface $riderRepository,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService,
        protected AppSettingService $appSettingService,
        protected DeliveryFeeCalculatorService $feeCalculator,
        protected WalletService $walletService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        $this->expireStaleOffers();

        return $this->deliveryRepository->datatable($filters);
    }

    public function findByUuid(string $uuid): ?Delivery
    {
        return $this->deliveryRepository->findByUuid($uuid);
    }

    public function findByTrackingNumber(string $trackingNumber): ?Delivery
    {
        return $this->deliveryRepository->findByTrackingNumber($trackingNumber);
    }

    public function create(array $data, ?int $userId = null): Delivery
    {
        return $this->transaction(function () use ($data, $userId) {
            $data['tracking_number'] = $this->deliveryRepository->generateTrackingNumber();
            $data['status'] = DeliveryStatus::PENDING;
            $data['offer_expires_at'] = now()->addMinutes($this->offerTimeoutMinutes());

            $shop = null;
            if (! empty($data['shop_id'])) {
                $shop = \App\Models\Shop::query()->find($data['shop_id']);
            }

            $estimate = $this->feeCalculator->estimate(
                pickupLat: isset($data['pickup_latitude']) ? (float) $data['pickup_latitude'] : null,
                pickupLng: isset($data['pickup_longitude']) ? (float) $data['pickup_longitude'] : null,
                dropLat: isset($data['latitude']) ? (float) $data['latitude'] : null,
                dropLng: isset($data['longitude']) ? (float) $data['longitude'] : null,
                pickupAddress: $data['pickup_address'] ?? null,
                dropAddress: $data['delivery_address'] ?? null,
                shop: $shop
            );

            $data['distance_km'] = $estimate['distance_km'];
            // Auto fee from distance; super_admin may override explicitly.
            $user = $userId ? \App\Models\User::query()->find($userId) : null;
            if (! ($user?->hasRole('super_admin') && array_key_exists('delivery_fee', $data) && $data['delivery_fee'] !== null && $data['delivery_fee'] !== '')) {
                $data['delivery_fee'] = $estimate['delivery_fee'];
            }
            $data['cod_amount'] = (float) ($data['cod_amount'] ?? 0);
            if (($data['payment_method'] ?? null) === 'cod' || $data['cod_amount'] > 0) {
                $data['payment_method'] = $data['payment_method'] ?: 'cod';
            }

            unset($data['pickup_latitude'], $data['pickup_longitude']);

            $delivery = $this->deliveryRepository->create(Arr::only($data, (new Delivery)->getFillable()));

            $this->recordStatusHistory($delivery, DeliveryStatus::PENDING, null, $userId);
            $this->auditLogService->log($userId, $delivery, 'created', null, $delivery->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::DELIVERY_CREATED,
                'Delivery created: '.$delivery->tracking_number,
                $delivery
            );

            event(new DeliveryUpdated($delivery->fresh(['shop', 'rider.user']), 'created'));

            return $delivery->load(['shop', 'rider.user']);
        });
    }

    public function update(string $uuid, array $data, ?int $userId = null): Delivery
    {
        return $this->transaction(function () use ($uuid, $data, $userId) {
            $delivery = $this->deliveryRepository->findByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            if ($delivery->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot update a delivery in a terminal state.'],
                ]);
            }

            unset($data['status'], $data['tracking_number'], $data['rider_id']);

            $oldValues = $delivery->toArray();
            $delivery = $this->deliveryRepository->update(
                $delivery->id,
                Arr::only($data, $delivery->getFillable())
            );

            $this->auditLogService->log($userId, $delivery, 'updated', $oldValues, $delivery->toArray());

            return $delivery->load(['shop', 'rider.user']);
        });
    }

    public function delete(string $uuid, ?int $userId = null): bool
    {
        return $this->transaction(function () use ($uuid, $userId) {
            $delivery = $this->deliveryRepository->findByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            $oldValues = $delivery->toArray();
            $deleted = $this->deliveryRepository->delete($delivery->id);

            $this->auditLogService->log($userId, $delivery, 'deleted', $oldValues, null);

            return $deleted;
        });
    }

    public function assignRider(string $uuid, int $riderId, ?int $userId = null, ?string $notes = null): Delivery
    {
        return $this->transaction(function () use ($uuid, $riderId, $userId, $notes) {
            $delivery = $this->deliveryRepository->findByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            if ($delivery->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot assign a rider to a terminal delivery.'],
                ]);
            }

            if (! in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Rider can only be assigned to pending or assigned deliveries.'],
                ]);
            }

            $rider = $this->riderRepository->find($riderId);

            if (! $rider || ! $rider->isPresentlyOnline() || ! $rider->is_available) {
                throw ValidationException::withMessages([
                    'rider_id' => ['Selected rider is not online in the app right now.'],
                ]);
            }

            $oldValues = $delivery->toArray();
            $previousStatus = $delivery->status;

            $delivery = $this->deliveryRepository->update($delivery->id, [
                'rider_id' => $riderId,
                'status' => DeliveryStatus::ASSIGNED,
                'assigned_at' => now(),
            ]);

            $this->riderRepository->update($riderId, [
                'is_available' => false,
            ]);

            $this->recordStatusHistory($delivery, DeliveryStatus::ASSIGNED, $previousStatus, $userId, $notes);
            $this->auditLogService->log($userId, $delivery, 'updated', $oldValues, $delivery->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::DELIVERY_ASSIGNED,
                'Rider assigned to delivery: '.$delivery->tracking_number,
                $delivery,
                ['rider_id' => $riderId]
            );

            event(new DeliveryUpdated($delivery->fresh(['shop', 'rider.user']), 'assigned'));

            return $delivery->load(['shop', 'rider.user']);
        });
    }

    public function rejectAssignment(string $uuid, ?int $userId = null, ?string $notes = null): Delivery
    {
        return $this->transaction(function () use ($uuid, $userId, $notes) {
            $delivery = $this->deliveryRepository->findByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            if ($delivery->status !== DeliveryStatus::ASSIGNED) {
                throw ValidationException::withMessages([
                    'status' => ['Only assigned deliveries can be rejected.'],
                ]);
            }

            $riderId = $delivery->rider_id;
            $oldValues = $delivery->toArray();
            $previousStatus = $delivery->status;

            $delivery = $this->deliveryRepository->update($delivery->id, [
                'rider_id' => null,
                'status' => DeliveryStatus::PENDING,
                'assigned_at' => null,
            ]);

            if ($riderId) {
                $this->riderRepository->update($riderId, [
                    'is_available' => true,
                ]);
            }

            $this->recordStatusHistory(
                $delivery,
                DeliveryStatus::PENDING,
                $previousStatus,
                $userId,
                $notes ?: 'Rider rejected the assignment.'
            );
            $this->auditLogService->log($userId, $delivery, 'updated', $oldValues, $delivery->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::DELIVERY_STATUS_CHANGED,
                'Rider rejected assignment: '.$delivery->tracking_number,
                $delivery,
                ['previous_status' => $previousStatus, 'new_status' => DeliveryStatus::PENDING]
            );

            event(new DeliveryUpdated($delivery->fresh(['shop', 'rider.user']), 'assignment_rejected'));

            return $delivery->load(['shop', 'rider.user']);
        });
    }

    public function updateStatus(string $uuid, string $status, ?int $userId = null, ?string $notes = null): Delivery
    {
        return $this->transaction(function () use ($uuid, $status, $userId, $notes) {
            $delivery = $this->deliveryRepository->findByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            if ($delivery->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => ['Cannot change status of a terminal delivery.'],
                ]);
            }

            if (! DeliveryStatus::canTransition($delivery->status, $status)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition from {$delivery->status} to {$status}."],
                ]);
            }

            $oldValues = $delivery->toArray();
            $previousStatus = $delivery->status;
            $updateData = ['status' => $status];

            match ($status) {
                DeliveryStatus::PICKED_UP => $updateData['picked_up_at'] = now(),
                DeliveryStatus::DELIVERED => $updateData['delivered_at'] = now(),
                DeliveryStatus::COMPLETED => $updateData['completed_at'] = now(),
                default => null,
            };

            $delivery = $this->deliveryRepository->update($delivery->id, $updateData);

            if ($status === DeliveryStatus::COMPLETED && $delivery->rider_id) {
                $rider = $this->riderRepository->find($delivery->rider_id);
                if ($rider) {
                    $this->riderRepository->update($rider->id, [
                        'total_deliveries' => $rider->total_deliveries + 1,
                        'is_available' => true,
                    ]);
                }
            }

            if (in_array($status, [DeliveryStatus::DELIVERED, DeliveryStatus::COMPLETED], true)) {
                $delivery = $this->walletService->settleCompletedDelivery($delivery, $userId);
            }

            if ($status === DeliveryStatus::COMPLETED) {
                $this->notifyShopDeliveryCompleted($delivery->fresh());
            }

            if ($status === DeliveryStatus::CANCELLED && $delivery->rider_id) {
                $this->riderRepository->update($delivery->rider_id, [
                    'is_available' => true,
                ]);
            }

            $this->recordStatusHistory($delivery, $status, $previousStatus, $userId, $notes);
            $this->auditLogService->log($userId, $delivery, 'updated', $oldValues, $delivery->toArray());
            $this->activityLogService->log(
                $userId,
                $this->resolveActivityType($status),
                "Delivery status changed to {$status}: ".$delivery->tracking_number,
                $delivery,
                ['previous_status' => $previousStatus, 'new_status' => $status]
            );

            event(new DeliveryUpdated($delivery->fresh(['shop', 'rider.user', 'statusHistories.changedBy']), 'status_updated'));

            return $delivery->load(['shop', 'rider.user', 'statusHistories.changedBy']);
        });
    }

    public function getAvailableOffers(): Collection
    {
        $this->expireStaleOffers();

        return $this->deliveryRepository->getUnclaimedPending(20);
    }

    public function expireStaleOffers(?int $userId = null): int
    {
        $timeoutMinutes = $this->offerTimeoutMinutes();
        $expiredCount = 0;

        $candidates = Delivery::query()
            ->where('status', DeliveryStatus::PENDING)
            ->whereNull('rider_id')
            ->where(function ($query) use ($timeoutMinutes) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('offer_expires_at')
                        ->where('offer_expires_at', '<=', now());
                })->orWhere(function ($inner) use ($timeoutMinutes) {
                    $inner->whereNull('offer_expires_at')
                        ->where('created_at', '<=', now()->subMinutes($timeoutMinutes));
                });
            })
            ->get();

        foreach ($candidates as $delivery) {
            $this->updateStatus(
                $delivery->uuid,
                DeliveryStatus::CANCELLED,
                $userId,
                'Auto-expired: no rider accepted within '.$timeoutMinutes.' minutes.'
            );
            $expiredCount++;
        }

        return $expiredCount;
    }

    public function claimDelivery(string $uuid, int $riderId, ?int $userId = null): Delivery
    {
        $this->expireStaleOffers($userId);

        $delivery = $this->deliveryRepository->findByUuid($uuid);

        if (! $delivery) {
            abort(404, 'Delivery not found.');
        }

        if ($delivery->status !== DeliveryStatus::PENDING || $delivery->rider_id !== null) {
            throw ValidationException::withMessages([
                'delivery' => ['This delivery is no longer available.'],
            ]);
        }

        if ($delivery->isOfferExpired()) {
            $this->updateStatus(
                $delivery->uuid,
                DeliveryStatus::CANCELLED,
                $userId,
                'Auto-expired: no rider accepted within '.$this->offerTimeoutMinutes().' minutes.'
            );

            throw ValidationException::withMessages([
                'delivery' => ['This delivery offer has expired.'],
            ]);
        }

        return $this->transaction(function () use ($uuid, $riderId, $userId) {
            $delivery = $this->deliveryRepository->findForUpdateByUuid($uuid);

            if (! $delivery) {
                abort(404, 'Delivery not found.');
            }

            if ($delivery->status !== DeliveryStatus::PENDING || $delivery->rider_id !== null) {
                throw ValidationException::withMessages([
                    'delivery' => ['This delivery is no longer available.'],
                ]);
            }

            $rider = $this->riderRepository->find($riderId);

            if (! $rider) {
                throw ValidationException::withMessages([
                    'rider_id' => ['Rider not found.'],
                ]);
            }

            if (! $rider->isPresentlyOnline() || ! $rider->is_available) {
                throw ValidationException::withMessages([
                    'rider_id' => ['You must be online in the app to accept deliveries.'],
                ]);
            }

            if ($rider->approval_status && $rider->approval_status !== ApprovalStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'rider_id' => ['Your rider account is not approved yet.'],
                ]);
            }

            $oldValues = $delivery->toArray();
            $previousStatus = $delivery->status;

            $delivery = $this->deliveryRepository->update($delivery->id, [
                'rider_id' => $riderId,
                'status' => DeliveryStatus::ASSIGNED,
                'assigned_at' => now(),
            ]);

            $this->riderRepository->update($riderId, [
                'is_available' => false,
            ]);

            $this->recordStatusHistory(
                $delivery,
                DeliveryStatus::ASSIGNED,
                $previousStatus,
                $userId,
                'Rider accepted delivery offer.'
            );

            $delivery = $this->deliveryRepository->update($delivery->id, [
                'status' => DeliveryStatus::ACCEPTED,
            ]);

            $this->recordStatusHistory(
                $delivery,
                DeliveryStatus::ACCEPTED,
                DeliveryStatus::ASSIGNED,
                $userId,
                'Rider accepted delivery offer.'
            );

            $this->auditLogService->log($userId, $delivery, 'updated', $oldValues, $delivery->toArray());
            $this->activityLogService->log(
                $userId,
                ActivityType::DELIVERY_ACCEPTED,
                'Rider accepted delivery offer: '.$delivery->tracking_number,
                $delivery,
                ['rider_id' => $riderId]
            );

            event(new DeliveryUpdated($delivery->fresh(['shop', 'rider.user']), 'claimed'));

            return $delivery->load(['shop', 'rider.user']);
        });
    }

    public function export(array $filters = []): Collection
    {
        return $this->deliveryRepository->datatableQuery($filters)->get();
    }

    protected function recordStatusHistory(
        Delivery $delivery,
        string $status,
        ?string $previousStatus,
        ?int $userId,
        ?string $notes = null
    ): DeliveryStatusHistory {
        return DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'status' => $status,
            'previous_status' => $previousStatus,
            'notes' => $notes,
            'changed_by' => $userId,
        ]);
    }

    protected function resolveActivityType(string $status): string
    {
        return match ($status) {
            DeliveryStatus::ASSIGNED => ActivityType::DELIVERY_ASSIGNED,
            DeliveryStatus::ACCEPTED => ActivityType::DELIVERY_ACCEPTED,
            DeliveryStatus::PICKED_UP => ActivityType::DELIVERY_PICKED_UP,
            DeliveryStatus::ON_THE_WAY => ActivityType::DELIVERY_ON_THE_WAY,
            DeliveryStatus::DELIVERED => ActivityType::DELIVERY_DELIVERED,
            DeliveryStatus::COMPLETED => ActivityType::DELIVERY_COMPLETED,
            DeliveryStatus::CANCELLED => ActivityType::DELIVERY_CANCELLED,
            default => ActivityType::DELIVERY_STATUS_CHANGED,
        };
    }

    protected function notifyShopDeliveryCompleted(Delivery $delivery): void
    {
        $delivery->loadMissing(['shop.user', 'rider.user']);
        $shopUser = $delivery->shop?->user;

        if (! $shopUser) {
            return;
        }

        $shopUser->notify(new DeliveryCompletedNotification($delivery));
    }

    protected function offerTimeoutMinutes(): int
    {
        return max(1, (int) $this->appSettingService->get('delivery_offer_timeout_minutes', 15));
    }
}

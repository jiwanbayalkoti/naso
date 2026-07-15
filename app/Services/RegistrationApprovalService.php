<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Helpers\ApprovalStatus;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class RegistrationApprovalService extends BaseService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected AuditLogService $auditLogService
    ) {}

    public function pendingCount(): int
    {
        return Shop::where('approval_status', ApprovalStatus::PENDING)->count()
            + Rider::where('approval_status', ApprovalStatus::PENDING)->count();
    }

    public function listPending(array $filters = []): LengthAwarePaginator
    {
        $items = $this->pendingRequests($filters);
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function findShopRequest(string $uuid): ?Shop
    {
        return Shop::with(['user', 'verificationDocuments'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findRiderRequest(string $uuid): ?Rider
    {
        return Rider::with(['user', 'verificationDocuments'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function approveShop(string $uuid, int $actorId): Shop
    {
        return $this->transaction(function () use ($uuid, $actorId) {
            $shop = Shop::with(['user', 'verificationDocuments'])->where('uuid', $uuid)->firstOrFail();

            if ($shop->approval_status !== ApprovalStatus::PENDING) {
                abort(422, 'This shop registration is not pending approval.');
            }

            $shop->update([
                'approval_status' => ApprovalStatus::APPROVED,
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => $actorId,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            $shop->user?->update([
                'is_active' => true,
                'updated_by' => $actorId,
            ]);

            $this->approveDocuments($shop);

            $this->auditLogService->log($actorId, $shop, 'approved', null, $shop->fresh()->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::SHOP_REGISTRATION_APPROVED,
                'Shop registration approved: '.$shop->name,
                $shop
            );

            return $shop->fresh(['user', 'verificationDocuments']);
        });
    }

    public function rejectShop(string $uuid, string $reason, int $actorId): Shop
    {
        return $this->transaction(function () use ($uuid, $reason, $actorId) {
            $shop = Shop::with(['user', 'verificationDocuments'])->where('uuid', $uuid)->firstOrFail();

            if ($shop->approval_status !== ApprovalStatus::PENDING) {
                abort(422, 'This shop registration is not pending approval.');
            }

            $shop->update([
                'approval_status' => ApprovalStatus::REJECTED,
                'is_active' => false,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $shop->user?->update([
                'is_active' => false,
                'updated_by' => $actorId,
            ]);

            $this->rejectDocuments($shop);

            $this->auditLogService->log($actorId, $shop, 'rejected', null, $shop->fresh()->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::SHOP_REGISTRATION_REJECTED,
                'Shop registration rejected: '.$shop->name,
                $shop
            );

            return $shop->fresh(['user', 'verificationDocuments']);
        });
    }

    public function approveRider(string $uuid, int $actorId): Rider
    {
        return $this->transaction(function () use ($uuid, $actorId) {
            $rider = Rider::with(['user', 'verificationDocuments'])->where('uuid', $uuid)->firstOrFail();

            if ($rider->approval_status !== ApprovalStatus::PENDING) {
                abort(422, 'This rider registration is not pending approval.');
            }

            $rider->update([
                'approval_status' => ApprovalStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $actorId,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            $rider->user?->update([
                'is_active' => true,
                'updated_by' => $actorId,
            ]);

            $this->approveDocuments($rider);

            $this->auditLogService->log($actorId, $rider, 'approved', null, $rider->fresh()->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::RIDER_REGISTRATION_APPROVED,
                'Rider registration approved: '.$rider->user?->name,
                $rider
            );

            return $rider->fresh(['user', 'verificationDocuments']);
        });
    }

    public function rejectRider(string $uuid, string $reason, int $actorId): Rider
    {
        return $this->transaction(function () use ($uuid, $reason, $actorId) {
            $rider = Rider::with(['user', 'verificationDocuments'])->where('uuid', $uuid)->firstOrFail();

            if ($rider->approval_status !== ApprovalStatus::PENDING) {
                abort(422, 'This rider registration is not pending approval.');
            }

            $rider->update([
                'approval_status' => ApprovalStatus::REJECTED,
                'approved_at' => null,
                'approved_by' => null,
                'rejection_reason' => $reason,
                'updated_by' => $actorId,
            ]);

            $rider->user?->update([
                'is_active' => false,
                'updated_by' => $actorId,
            ]);

            $this->rejectDocuments($rider);

            $this->auditLogService->log($actorId, $rider, 'rejected', null, $rider->fresh()->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::RIDER_REGISTRATION_REJECTED,
                'Rider registration rejected: '.$rider->user?->name,
                $rider
            );

            return $rider->fresh(['user', 'verificationDocuments']);
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function pendingRequests(array $filters = []): Collection
    {
        $search = $filters['search'] ?? null;

        $shops = Shop::with('user')
            ->withCount('verificationDocuments')
            ->where('approval_status', ApprovalStatus::PENDING)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->get()
            ->map(fn (Shop $shop) => $this->transformShop($shop));

        $riders = Rider::with('user')
            ->withCount('verificationDocuments')
            ->where('approval_status', ApprovalStatus::PENDING)
            ->when($search, function ($query) use ($search) {
                $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            })
            ->get()
            ->map(fn (Rider $rider) => $this->transformRider($rider));

        $type = $filters['type'] ?? null;

        $items = match ($type) {
            'shop' => $shops,
            'rider' => $riders,
            default => $shops->concat($riders),
        };

        return $items->sortByDesc('submitted_at_sort')->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformShop(Shop $shop): array
    {
        return [
            'uuid' => $shop->uuid,
            'type' => 'shop',
            'type_label' => 'Shop',
            'name' => $shop->name,
            'email' => $shop->user?->email,
            'phone' => $shop->user?->phone ?? $shop->phone,
            'approval_status' => $shop->approval_status,
            'documents_count' => $shop->verification_documents_count,
            'submitted_at' => $shop->created_at?->format('M d, Y H:i'),
            'submitted_at_sort' => $shop->created_at?->timestamp ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformRider(Rider $rider): array
    {
        return [
            'uuid' => $rider->uuid,
            'type' => 'rider',
            'type_label' => 'Rider',
            'name' => $rider->user?->name,
            'email' => $rider->user?->email,
            'phone' => $rider->user?->phone,
            'approval_status' => $rider->approval_status,
            'documents_count' => $rider->verification_documents_count,
            'submitted_at' => $rider->created_at?->format('M d, Y H:i'),
            'submitted_at_sort' => $rider->created_at?->timestamp ?? 0,
        ];
    }

    protected function approveDocuments(Shop|Rider $owner): void
    {
        $owner->verificationDocuments()->update(['status' => 'approved']);
    }

    protected function rejectDocuments(Shop|Rider $owner): void
    {
        $owner->verificationDocuments()->update(['status' => 'rejected']);
    }
}

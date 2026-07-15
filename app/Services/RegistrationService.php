<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Helpers\ApprovalStatus;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\Contracts\RiderRepositoryInterface;
use App\Repositories\Contracts\ShopRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationService extends BaseService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected ShopRepositoryInterface $shopRepository,
        protected RiderRepositoryInterface $riderRepository,
        protected ActivityLogService $activityLogService,
        protected AuditLogService $auditLogService,
        protected DocumentUploadService $documentUploadService
    ) {}

    /**
     * @return array{user: User, shop: Shop}
     */
    public function registerShop(array $data, bool $login = true, bool $requiresApproval = true): array
    {
        return $this->transaction(function () use ($data, $login, $requiresApproval) {
            if ($this->userRepository->findByEmail($data['owner_email'])) {
                throw ValidationException::withMessages([
                    'owner_email' => ['This email is already registered.'],
                ]);
            }

            $user = $this->userRepository->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => Hash::make($data['owner_password']),
                'is_active' => ! $requiresApproval,
            ]);

            $user->assignRole('shop');

            if ($this->shopRepository->findByUserId($user->id)) {
                throw ValidationException::withMessages([
                    'owner_email' => ['This account already has a shop.'],
                ]);
            }

            $shop = $this->shopRepository->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(4),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'description' => $data['description'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'nid_number' => $data['nid_number'] ?? null,
                'is_active' => ! $requiresApproval,
                'approval_status' => $requiresApproval ? ApprovalStatus::PENDING : ApprovalStatus::APPROVED,
                'approved_at' => $requiresApproval ? null : now(),
                'created_by' => $user->id,
            ]);

            $this->storeVerificationDocuments($shop, $data, $user->id);

            $this->auditLogService->log($user->id, $shop, 'created', null, $shop->toArray());
            $this->activityLogService->log(
                $user->id,
                ActivityType::SHOP_REGISTERED,
                $requiresApproval
                    ? 'Shop registration submitted for approval: '.$shop->name
                    : 'Shop registered: '.$shop->name,
                $shop
            );

            if ($login && ! $requiresApproval) {
                Auth::login($user);
            }

            return [
                'user' => $user->load('roles'),
                'shop' => $shop->load(['user', 'verificationDocuments']),
                'requires_approval' => $requiresApproval,
            ];
        });
    }

    /**
     * @return array{user: User, rider: Rider}
     */
    public function registerRider(array $data, bool $login = true, bool $requiresApproval = true): array
    {
        return $this->transaction(function () use ($data, $login, $requiresApproval) {
            if ($this->userRepository->findByEmail($data['email'])) {
                throw ValidationException::withMessages([
                    'email' => ['This email is already registered.'],
                ]);
            }

            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => ! $requiresApproval,
            ]);

            $user->assignRole('rider');

            if ($this->riderRepository->findByUserId($user->id)) {
                throw ValidationException::withMessages([
                    'email' => ['This account is already registered as a rider.'],
                ]);
            }

            $rider = $this->riderRepository->create([
                'user_id' => $user->id,
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'license_number' => $data['license_number'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'nid_number' => $data['nid_number'] ?? null,
                'approval_status' => $requiresApproval ? ApprovalStatus::PENDING : ApprovalStatus::APPROVED,
                'approved_at' => $requiresApproval ? null : now(),
                'is_online' => false,
                'is_available' => true,
                'created_by' => $user->id,
            ]);

            $this->storeVerificationDocuments($rider, $data, $user->id);

            $this->auditLogService->log($user->id, $rider, 'created', null, $rider->toArray());
            $this->activityLogService->log(
                $user->id,
                ActivityType::RIDER_REGISTERED,
                $requiresApproval
                    ? 'Rider registration submitted for approval: '.$user->name
                    : 'Rider registered: '.$user->name,
                $rider
            );

            if ($login && ! $requiresApproval) {
                Auth::login($user);
            }

            return [
                'user' => $user->load('roles'),
                'rider' => $rider->load(['user', 'verificationDocuments']),
                'requires_approval' => $requiresApproval,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeVerificationDocuments($owner, array $data, int $userId): void
    {
        $documents = collect($data['documents'] ?? [])
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->all();

        if (empty($documents)) {
            return;
        }

        $this->documentUploadService->storeMany(
            $owner,
            $documents,
            [
                'pan_number' => $data['pan_number'] ?? null,
                'nid_number' => $data['nid_number'] ?? null,
                'license_number' => $data['license_number'] ?? null,
            ],
            $userId
        );
    }
}

<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuditLogService $auditLogService,
        protected ActivityLogService $activityLogService
    ) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->userRepository->datatable($filters);
    }

    public function findByUuid(string $uuid): ?User
    {
        return $this->userRepository->findByUuid($uuid);
    }

    public function create(array $data, ?int $actorId = null): User
    {
        return $this->transaction(function () use ($data, $actorId) {
            $role = $data['role'] ?? null;
            unset($data['role']);

            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user = $this->userRepository->create($data);

            if ($role) {
                $user->assignRole($role);
            }

            $this->auditLogService->log($actorId, $user, 'created', null, $user->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::USER_CREATED,
                'User created: '.$user->email,
                $user
            );

            return $user->load('roles');
        });
    }

    public function update(string $uuid, array $data, ?int $actorId = null): User
    {
        return $this->transaction(function () use ($uuid, $data, $actorId) {
            $user = $this->userRepository->findByUuid($uuid);

            if (! $user) {
                abort(404, 'User not found.');
            }

            $role = $data['role'] ?? null;
            unset($data['role']);

            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $oldValues = $user->toArray();
            $user = $this->userRepository->update($user->id, Arr::only($data, $user->getFillable()));

            if ($role) {
                $user->syncRoles([$role]);
            }

            $this->auditLogService->log($actorId, $user, 'updated', $oldValues, $user->toArray());
            $this->activityLogService->log(
                $actorId,
                ActivityType::USER_UPDATED,
                'User updated: '.$user->email,
                $user
            );

            return $user->load('roles');
        });
    }

    public function delete(string $uuid, ?int $actorId = null): bool
    {
        return $this->transaction(function () use ($uuid, $actorId) {
            $user = $this->userRepository->findByUuid($uuid);

            if (! $user) {
                abort(404, 'User not found.');
            }

            $oldValues = $user->toArray();
            $deleted = $this->userRepository->delete($user->id);

            $this->auditLogService->log($actorId, $user, 'deleted', $oldValues, null);

            return $deleted;
        });
    }

    public function export(array $filters = []): Collection
    {
        return $this->userRepository->datatableQuery($filters)->get();
    }
}

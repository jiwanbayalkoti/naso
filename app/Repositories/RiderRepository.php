<?php

namespace App\Repositories;

use App\Helpers\ApprovalStatus;
use App\Models\Rider;
use App\Repositories\Contracts\RiderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RiderRepository extends BaseRepository implements RiderRepositoryInterface
{
    protected array $searchableColumns = ['vehicle_type', 'vehicle_number', 'license_number'];

    protected array $sortableColumns = ['vehicle_type', 'is_online', 'is_available', 'rating', 'total_deliveries', 'created_at'];

    protected function resolveModel(): Model
    {
        return new Rider;
    }

    public function datatableQuery(array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $search = $filters['search'] ?? null;
        unset($filters['search']);

        $query = parent::datatableQuery($filters)->with('user');

        if (! empty($search)) {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $builder) use ($search) {
                $builder->where('vehicle_type', 'like', '%'.$search.'%')
                    ->orWhere('vehicle_number', 'like', '%'.$search.'%')
                    ->orWhere('license_number', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (\Illuminate\Database\Eloquent\Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        return $query;
    }

    public function findByUserId(int $userId): ?Rider
    {
        return $this->newQuery()->where('user_id', $userId)->first();
    }

    public function getOnlineAvailable(): Collection
    {
        return $this->newQuery()
            ->presentOnline()
            ->where('is_available', true)
            ->with('user')
            ->get();
    }

    public function getOnline(): Collection
    {
        return $this->newQuery()
            ->presentOnline()
            ->with('user')
            ->get();
    }

    public function getAssignable(): Collection
    {
        return $this->newQuery()
            ->where(function ($query) {
                $query->where('approval_status', ApprovalStatus::APPROVED)
                    ->orWhereNull('approval_status');
            })
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->with('user')
            ->orderByRaw('CASE WHEN is_online = 1 AND last_seen_at IS NOT NULL AND last_seen_at >= ? THEN 0 ELSE 1 END', [
                now()->subMinutes((int) config('naso.rider_presence_minutes', 5)),
            ])
            ->orderByDesc('is_available')
            ->get();
    }
}

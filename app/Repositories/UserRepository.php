<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected array $searchableColumns = ['name', 'email', 'phone'];

    protected array $sortableColumns = ['name', 'email', 'phone', 'is_active', 'created_at'];

    protected function resolveModel(): Model
    {
        return new User;
    }

    public function datatableQuery(array $filters = []): Builder
    {
        $query = parent::datatableQuery($filters)->with('roles');

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn (Builder $builder) => $builder->where('name', $filters['role']));
        }

        return $query;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->newQuery()->where('email', $email)->first();
    }

    public function getActive(): Collection
    {
        return $this->newQuery()->where('is_active', true)->get();
    }
}

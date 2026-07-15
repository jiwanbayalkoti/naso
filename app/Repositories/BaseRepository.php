<?php

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Model $model;

    /**
     * Columns searchable in datatable queries.
     *
     * @var array<int, string>
     */
    protected array $searchableColumns = [];

    /**
     * Columns sortable in datatable queries.
     *
     * @var array<int, string>
     */
    protected array $sortableColumns = ['created_at'];

    public function __construct()
    {
        $this->model = $this->resolveModel();
    }

    /**
     * Resolve the repository model instance.
     */
    abstract protected function resolveModel(): Model;

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->find($id, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->where('uuid', $uuid)->first($columns);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Model
    {
        return $this->newQuery()->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(int|string $id, array $data): Model
    {
        $record = $this->findOrFail($id);
        $record->update($data);

        return $record->fresh();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int|string $id): bool
    {
        $record = $this->findOrFail($id);

        return (bool) $record->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage, $columns);
    }

    /**
     * {@inheritdoc}
     */
    public function datatableQuery(array $filters = []): Builder
    {
        $query = $this->newQuery();

        if (! empty($filters['search']) && ! empty($this->searchableColumns)) {
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($search) {
                foreach ($this->searchableColumns as $column) {
                    $builder->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        foreach ($filters as $key => $value) {
            if (in_array($key, ['search', 'sort_by', 'sort_direction', 'per_page', 'page'], true)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if ($this->model->isFillable($key) || array_key_exists($key, $this->model->getCasts())) {
                $query->where($key, $value);
            }
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, $this->sortableColumns, true)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }

    /**
     * Paginate a datatable query.
     */
    public function datatable(array $filters = [], array $columns = ['*']): LengthAwarePaginator
    {
        $perPage = (int) Arr::get($filters, 'per_page', 15);

        return $this->datatableQuery($filters)->paginate($perPage, $columns);
    }

    /**
     * Get a new query builder instance.
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Find a record or fail.
     */
    protected function findOrFail(int|string $id): Model
    {
        return $this->newQuery()->findOrFail($id);
    }
}

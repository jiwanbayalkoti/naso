<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Find a record by primary key.
     */
    public function find(int|string $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by UUID.
     */
    public function findByUuid(string $uuid, array $columns = ['*']): ?Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update a record by primary key.
     */
    public function update(int|string $id, array $data): Model;

    /**
     * Delete a record by primary key.
     */
    public function delete(int|string $id): bool;

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Build a query for datatable listings.
     */
    public function datatableQuery(array $filters = []): Builder;
}

<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    protected array $searchableColumns = ['event', 'auditable_type'];

    protected array $sortableColumns = ['event', 'created_at'];

    protected function resolveModel(): Model
    {
        return new AuditLog;
    }

    public function datatableQuery(array $filters = []): Builder
    {
        return parent::datatableQuery($filters)->with(['user', 'auditable']);
    }
}

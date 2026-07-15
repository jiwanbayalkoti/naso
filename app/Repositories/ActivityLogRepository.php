<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogRepository extends BaseRepository implements ActivityLogRepositoryInterface
{
    protected array $searchableColumns = ['activity_type', 'description'];

    protected array $sortableColumns = ['activity_type', 'created_at'];

    protected function resolveModel(): Model
    {
        return new ActivityLog;
    }

    public function datatableQuery(array $filters = []): Builder
    {
        return parent::datatableQuery($filters)->with(['user', 'subject']);
    }
}

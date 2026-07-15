<?php

namespace App\Services;

use App\Jobs\RecordAuditLogJob;
use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AuditLogService extends BaseService
{
    public function __construct(
        protected AuditLogRepositoryInterface $auditLogRepository
    ) {}

    /**
     * Log a CRUD audit event (queued when QUEUE_CONNECTION is not sync).
     */
    public function log(
        ?int $userId,
        Model $auditable,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?AuditLog {
        $data = [
            'user_id' => $userId,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (config('queue.default') === 'sync') {
            return $this->createFromArray($data);
        }

        RecordAuditLogJob::dispatch(
            $userId,
            $data['auditable_type'],
            $data['auditable_id'],
            $event,
            $oldValues,
            $newValues
        );

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromArray(array $data): AuditLog
    {
        return $this->auditLogRepository->create($data);
    }
}

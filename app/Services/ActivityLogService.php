<?php

namespace App\Services;

use App\Jobs\RecordActivityLogJob;
use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService extends BaseService
{
    public function __construct(
        protected ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    /**
     * Log an activity event (queued when QUEUE_CONNECTION is not sync).
     */
    public function log(
        ?int $userId,
        string $type,
        ?string $description = null,
        ?Model $subject = null,
        ?array $properties = null
    ): ?ActivityLog {
        $data = [
            'user_id' => $userId,
            'activity_type' => $type,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($subject !== null) {
            $data['subject_type'] = $subject->getMorphClass();
            $data['subject_id'] = $subject->getKey();
        }

        if (config('queue.default') === 'sync') {
            return $this->createFromArray($data);
        }

        RecordActivityLogJob::dispatch(
            $userId,
            $type,
            $description,
            $data['subject_type'] ?? null,
            $data['subject_id'] ?? null,
            $properties,
            $data['ip_address'],
            $data['user_agent']
        );

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFromArray(array $data): ActivityLog
    {
        return $this->activityLogRepository->create($data);
    }
}

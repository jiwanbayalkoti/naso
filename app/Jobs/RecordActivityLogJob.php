<?php

namespace App\Jobs;

use App\Services\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordActivityLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>|null  $properties
     */
    public function __construct(
        public ?int $userId,
        public string $type,
        public ?string $description = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?array $properties = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {}

    public function handle(ActivityLogService $activityLogService): void
    {
        $subject = null;

        if ($this->subjectType && $this->subjectId) {
            $class = $this->subjectType;
            if (class_exists($class)) {
                $subject = $class::find($this->subjectId);
            }
        }

        $data = [
            'user_id' => $this->userId,
            'activity_type' => $this->type,
            'description' => $this->description,
            'properties' => $this->properties,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];

        if ($subject instanceof Model) {
            $data['subject_type'] = $subject->getMorphClass();
            $data['subject_id'] = $subject->getKey();
        }

        $activityLogService->createFromArray($data);
    }
}

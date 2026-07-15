<?php

namespace App\Jobs;

use App\Services\AuditLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordAuditLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public ?int $userId,
        public string $auditableType,
        public int $auditableId,
        public string $event,
        public ?array $oldValues = null,
        public ?array $newValues = null
    ) {}

    public function handle(AuditLogService $auditLogService): void
    {
        $auditLogService->createFromArray([
            'user_id' => $this->userId,
            'auditable_type' => $this->auditableType,
            'auditable_id' => $this->auditableId,
            'event' => $this->event,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'ip_address' => null,
            'user_agent' => null,
        ]);
    }
}

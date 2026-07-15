<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $oldValues = is_array($this->old_values) ? $this->old_values : [];
        $newValues = is_array($this->new_values) ? $this->new_values : [];
        $changedKeys = array_values(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
        $fieldCount = count($changedKeys);

        return [
            'uuid' => $this->uuid,
            'event' => $this->event,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'user' => new UserResource($this->whenLoaded('user')),
            'user_name' => $this->relationLoaded('user')
                ? ($this->user?->name ?? 'System')
                : 'System',
            'changes_summary' => $fieldCount > 0
                ? ($fieldCount.' field'.($fieldCount === 1 ? '' : 's').' changed')
                : null,
            'created_at' => $this->created_at?->format('M d, Y H:i'),
            'created_at_iso' => $this->created_at?->toIso8601String(),
        ];
    }
}

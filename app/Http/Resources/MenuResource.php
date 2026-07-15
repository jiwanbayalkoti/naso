<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'parent_id' => $this->parent_id,
            'parent_title' => $this->relationLoaded('parent') ? $this->parent?->title : null,
            'title' => $this->title,
            'icon' => $this->icon,
            'route_name' => $this->route_name,
            'url' => $this->url,
            'route_pattern' => $this->route_pattern,
            'permission' => $this->permission,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'resolved_url' => $this->resolved_url,
            'children' => MenuResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->format('M d, Y'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

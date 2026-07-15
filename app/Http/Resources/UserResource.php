<?php

namespace App\Http\Resources;

use App\Helpers\MediaUrlHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'avatar_url' => MediaUrlHelper::url($this->avatar),
            'is_active' => $this->is_active,
            'role' => $this->relationLoaded('roles') ? $this->roles->first()?->name : null,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'last_login_at' => null,
            'created_at' => $this->created_at?->format('M d, Y'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

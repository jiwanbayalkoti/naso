<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'audience',
        'type',
        'is_active',
        'priority',
        'starts_at',
        'ends_at',
        'window',
        'config',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'config' => 'array',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(OfferRedemption::class);
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }
}

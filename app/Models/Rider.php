<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rider extends Model
{
    use HasAuditColumns;
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'pan_number',
        'nid_number',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'is_online',
        'is_available',
        'current_latitude',
        'current_longitude',
        'location_updated_at',
        'last_seen_at',
        'rating',
        'total_deliveries',
        'balance',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'location_updated_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'rating' => 'decimal:2',
        'total_deliveries' => 'integer',
        'approved_at' => 'datetime',
        'balance' => 'decimal:2',
    ];

    /**
     * Rider toggled online AND recently active in the app.
     */
    public function isPresentlyOnline(?int $minutes = null): bool
    {
        if (! $this->is_online || ! $this->last_seen_at) {
            return false;
        }

        $minutes ??= (int) config('naso.rider_presence_minutes', 5);

        if ($this->last_seen_at->lt(now()->subMinutes($minutes))) {
            return false;
        }

        if ($this->relationLoaded('user') && $this->user && ! $this->user->is_active) {
            return false;
        }

        return true;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Rider>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Rider>
     */
    public function scopePresentOnline($query, ?int $minutes = null)
    {
        $minutes ??= (int) config('naso.rider_presence_minutes', 5);

        return $query
            ->where('is_online', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', now()->subMinutes($minutes))
            ->whereHas('user', function ($userQuery) {
                $userQuery->where('is_active', true);
            });
    }

    public function touchLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    /**
     * Drop live presence without changing the rider's Online preference.
     * Used on logout / auto away so a later login can restore Online.
     */
    public function clearPresence(): void
    {
        if ($this->last_seen_at === null) {
            return;
        }

        $this->forceFill(['last_seen_at' => null])->save();
    }

    /**
     * Manual offline preference — stays Offline after the next login.
     */
    public function markOffline(): void
    {
        $this->forceFill([
            'is_online' => false,
            'is_available' => false,
            'last_seen_at' => null,
        ])->save();
    }

    /**
     * Restore live presence after login when the rider prefers Online.
     */
    public function restorePresenceIfPreferred(): bool
    {
        if (! $this->is_online) {
            return false;
        }

        $this->forceFill([
            'is_available' => true,
            'last_seen_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Get the user associated with the rider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get deliveries assigned to the rider.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Get verification documents for the rider.
     */
    public function verificationDocuments(): MorphMany
    {
        return $this->morphMany(VerificationDocument::class, 'documentable');
    }
}

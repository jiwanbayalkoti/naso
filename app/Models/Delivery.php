<?php

namespace App\Models;

use App\Helpers\DeliveryStatus;
use App\Traits\HasAuditColumns;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
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
        'shop_id',
        'rider_id',
        'tracking_number',
        'customer_name',
        'customer_phone',
        'pickup_address',
        'delivery_address',
        'latitude',
        'longitude',
        'status',
        'offer_expires_at',
        'priority',
        'notes',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'completed_at',
        'delivery_fee',
        'payment_method',
        'payment_status',
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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'assigned_at' => 'datetime',
        'offer_expires_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_fee' => 'decimal:2',
    ];

    /**
     * Get the shop for the delivery.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the rider assigned to the delivery.
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get status history entries for the delivery.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class);
    }

    /**
     * Determine if the delivery is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            DeliveryStatus::COMPLETED,
            DeliveryStatus::CANCELLED,
        ], true);
    }

    public function isOfferExpired(): bool
    {
        if ($this->status !== DeliveryStatus::PENDING || $this->rider_id !== null) {
            return false;
        }

        if ($this->offer_expires_at) {
            return $this->offer_expires_at->isPast();
        }

        return false;
    }

    public function isOfferActive(): bool
    {
        return $this->status === DeliveryStatus::PENDING
            && $this->rider_id === null
            && ! $this->isOfferExpired();
    }
}

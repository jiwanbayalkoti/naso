<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRedemption extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'offer_id',
        'shop_id',
        'rider_id',
        'delivery_id',
        'benefit',
    ];

    protected $casts = [
        'benefit' => 'array',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }
}

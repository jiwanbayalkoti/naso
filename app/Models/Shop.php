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

class Shop extends Model
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
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'city',
        'latitude',
        'longitude',
        'logo',
        'is_active',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'description',
        'pan_number',
        'nid_number',
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
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the user who owns the shop.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get deliveries for the shop.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Get verification documents for the shop.
     */
    public function verificationDocuments(): MorphMany
    {
        return $this->morphMany(VerificationDocument::class, 'documentable');
    }

    public function payouts(): MorphMany
    {
        return $this->morphMany(Payout::class, 'payable');
    }
}

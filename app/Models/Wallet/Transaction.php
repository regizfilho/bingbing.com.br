<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Coupon;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'package_id',
        'gift_card_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'transactionable_type',
        'transactionable_id',
        'status',
        'refund_requested_at',
        'refunded_at',
        'coupon_id',
        'original_amount',
        'discount_amount',
        'final_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'refund_requested_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Scopes úteis
    public function scopeFromPackage($query, int $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeFromGiftCard($query, int $giftCardId)
    {
        return $query->where('gift_card_id', $giftCardId);
    }

    public function scopeWithPackageInfo($query)
    {
        return $query->with(['package', 'giftCard']);
    }
}
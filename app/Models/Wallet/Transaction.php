<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Coupon;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'wallet_id',
        'package_id',
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

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function wallet()
    {
        return $this->belongsTo(\App\Models\Wallet\Wallet::class);
    }

    public function transactionable()
    {
        return $this->morphTo();
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
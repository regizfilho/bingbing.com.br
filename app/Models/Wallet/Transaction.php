<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 'wallet_id', 'type', 'amount', 'balance_after', 'description',
        'transactionable_type', 'transactionable_id', 'status',
        'refund_requested_at', 'refunded_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'refund_requested_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactionable()
    {
        return $this->morphTo();
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
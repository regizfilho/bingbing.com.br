<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class GiftCardRedemption extends Model
{
    protected $fillable = [
        'uuid',
        'gift_card_id',
        'user_id',
        'credit_value',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'credit_value' => 'decimal:2',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
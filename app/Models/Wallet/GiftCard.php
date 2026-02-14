<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class GiftCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'code',
        'credit_value',
        'price_brl',
        'source',
        'description',
        'created_by_user_id',
        'status',
        'redeemed_by_user_id',
        'redeemed_at',
        'expires_at',
    ];

    protected $casts = [
        'credit_value' => 'decimal:2',
        'price_brl' => 'decimal:2',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function redeemedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(GiftCardRedemption::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isRedeemed(): bool
    {
        return $this->status === 'redeemed';
    }

    public function canBeRedeemed(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function markAsExpired(): void
    {
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4) . '-' .
                              substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4) . '-' .
                              substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where(function($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
                     ->orWhere(function($q) {
                         $q->where('status', 'active')
                           ->where('expires_at', '<=', now());
                     });
    }

    public function scopeRedeemed($query)
    {
        return $query->where('status', 'redeemed');
    }
}
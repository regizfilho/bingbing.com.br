<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
   protected $fillable = [
    'code',
    'description',
    'type',
    'value',
    'min_order_value',
    'expires_at',
    'usage_limit',
    'per_user_limit',
    'used_count',
    'is_active',
];

protected $casts = [
    'expires_at' => 'datetime',
    'is_active' => 'boolean',
];

public function users()
{
    return $this->belongsToMany(User::class, 'coupon_users')
        ->withPivot([
            'used_at',
            'order_value',
            'discount_amount',
            'ip_address'
        ])
        ->withTimestamps();
}

public function getRemainingAttribute()
{
    if (!$this->usage_limit) return '∞';
    return max(0, $this->usage_limit - $this->used_count);
}

public function validateForUser($user, float $orderValue)
{
    if (!$this->is_active) {
        return 'Cupom inativo.';
    }

    if ($this->expires_at && $this->expires_at->isPast()) {
        return 'Cupom expirado.';
    }

    if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
        return 'Limite global do cupom atingido.';
    }

    if ($this->min_order_value && $orderValue < $this->min_order_value) {
        return 'Valor mínimo para uso do cupom não atingido.';
    }

    if ($this->per_user_limit) {
        $usedByUser = $this->users()
            ->where('user_id', $user->id)
            ->count();

        if ($usedByUser >= $this->per_user_limit) {
            return 'Você já utilizou este cupom o limite permitido.';
        }
    }

    return true;
}


}

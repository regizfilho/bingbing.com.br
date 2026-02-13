<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponUser extends Model
{
    protected $fillable = [
    'coupon_id',
    'user_id',
    'used_at',
    'order_value',
    'discount_amount',
    'ip_address',
];

protected $casts = [
    'used_at' => 'datetime',
];


    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['name', 'credits', 'price_brl', 'is_active', 'order'];
    
    protected $casts = [
        'credits' => 'decimal:2',
        'price_brl' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}
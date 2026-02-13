<?php

namespace App\Models\Wallet;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = ['name', 'credits', 'price_brl', 'is_active', 'order'];
    
    protected $casts = [
        'credits' => 'integer',
        'price_brl' => 'decimal:2',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'package_id');
    }
}
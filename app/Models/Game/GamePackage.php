<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class GamePackage extends Model
{
    protected $fillable = [
        'name', 'slug', 'cost_credits', 'max_players', 'max_cards_per_player',
        'max_winners_per_prize', 'can_customize_prizes', 'features',
        'is_free', 'is_active', 'order'
    ];

    protected $casts = [
        'cost_credits' => 'decimal:2',
        'can_customize_prizes' => 'boolean',
        'features' => 'array',
        'is_free' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}
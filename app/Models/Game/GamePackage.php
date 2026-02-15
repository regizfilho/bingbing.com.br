<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class GamePackage extends Model
{
    protected $table = 'game_packages';

    protected $fillable = [
        'name',
        'slug',
        'cost_credits',
        'is_free',
        'max_players',
        'max_rounds',
        'max_cards_per_player',
        'cards_per_player',
        'max_winners_per_prize',
        'can_customize_prizes',
        'allowed_card_sizes',
        'features',
        'daily_limit',
        'monthly_limit',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'is_active' => 'boolean',
        'can_customize_prizes' => 'boolean',
        'allowed_card_sizes' => 'array',
        'features' => 'array',
        'cost_credits' => 'float',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Player extends Model
{
    protected $fillable = ['game_id', 'user_id', 'joined_at'];
    
    protected $casts = ['joined_at' => 'datetime'];

    protected static function booted()
    {
        static::created(function ($player) {
            $max = $player->game->package->max_cards_per_player;
            
            for ($i = 0; $i < $max; $i++) {
                $player->generateCard();
            }
        });
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    private function generateCard()
    {
        $numbers = collect(range(1, 75))->shuffle()->take(24)->sort()->values();
        
        return $this->cards()->create([
            'numbers' => $numbers,
            'marked' => [],
        ]);
    }
}
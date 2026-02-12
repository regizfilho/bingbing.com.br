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
            // Pega a quantidade configurada NA PARTIDA (se não tiver, usa 1)
            $count = $player->game->cards_per_player ?? 1;
            $cardSize = $player->game->card_size ?? 24;

            for ($i = 0; $i < $count; $i++) {
                $player->generateCardForRound(1, $cardSize);
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

    public function generateCardForRound(int $roundNumber, int $cardSize)
    {
        $numbers = collect(range(1, 75))->shuffle()->take($cardSize)->sort()->values();

        return $this->cards()->create([
            'game_id' => $this->game_id,
            'numbers' => $numbers,
            'marked' => [],
            'round_number' => $roundNumber,
        ]);
    }
}
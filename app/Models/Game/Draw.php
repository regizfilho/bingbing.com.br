<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;

class Draw extends Model
{
    protected $fillable = ['game_id', 'number', 'sequence', 'drawn_at'];
    
    protected $casts = ['drawn_at' => 'datetime'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
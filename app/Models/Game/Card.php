<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Card extends Model
{
    use HasUuids;

    protected $fillable = ['uuid', 'player_id', 'numbers', 'marked', 'is_bingo', 'bingo_at'];
    
    protected $casts = [
        'numbers' => 'array',
        'marked' => 'array',
        'is_bingo' => 'boolean',
        'bingo_at' => 'datetime',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function winner()
    {
        return $this->hasOne(Winner::class);
    }

    public function markNumber(int $number): bool
    {
        if (!in_array($number, $this->numbers)) {
            return false;
        }

        $marked = $this->marked ?? [];
        
        if (!in_array($number, $marked)) {
            $marked[] = $number;
            $this->update(['marked' => $marked]);
        }

        return true;
    }

    public function checkBingo(array $drawnNumbers): bool
    {
        $cardNumbers = $this->numbers;
        $matched = array_intersect($cardNumbers, $drawnNumbers);
        
        return count($matched) === count($cardNumbers);
    }

    public function setBingo(): void
    {
        $this->update([
            'is_bingo' => true,
            'bingo_at' => now(),
        ]);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;
use Illuminate\Support\Str;

class Game extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 'creator_id', 'game_package_id', 'name', 'invite_code',
        'draw_mode', 'auto_draw_seconds', 'status', 'started_at', 'finished_at'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected static function booted()
    {
        static::creating(function ($game) {
            $game->invite_code = strtoupper(Str::random(12));
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function package()
    {
        return $this->belongsTo(GamePackage::class, 'game_package_id');
    }

    public function prizes()
    {
        return $this->hasMany(Prize::class)->orderBy('position');
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function draws()
    {
        return $this->hasMany(Draw::class)->orderBy('sequence');
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }

    public function canJoin(): bool
    {
        return $this->status === 'waiting' && 
               $this->players()->count() < $this->package->max_players;
    }

    public function drawNumber(): ?Draw
    {
        if ($this->status !== 'active') {
            return null;
        }

        $drawnNumbers = $this->draws->pluck('number')->toArray();
        $available = array_diff(range(1, 75), $drawnNumbers);

        if (empty($available)) {
            return null;
        }

        $number = collect($available)->random();
        $sequence = $this->draws()->max('sequence') + 1;

        return $this->draws()->create([
            'number' => $number,
            'sequence' => $sequence,
            'drawn_at' => now(),
        ]);
    }

    public function checkWinningCards()
    {
        $drawnNumbers = $this->draws->pluck('number')->toArray();
        
        return $this->players()
            ->with('cards')
            ->get()
            ->flatMap->cards
            ->filter(fn($card) => !$card->is_bingo && $card->checkBingo($drawnNumbers));
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
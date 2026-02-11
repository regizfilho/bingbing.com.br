<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

class Winner extends Model
{
    use HasUuids;

    protected $fillable = ['uuid', 'game_id', 'prize_id', 'card_id', 'user_id', 'won_at'];
    
    protected $casts = ['won_at' => 'datetime'];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected static function booted()
    {
        static::created(function ($winner) {
            $winner->user->rank->increment('total_wins');
            $winner->user->rank->increment('weekly_wins');
            $winner->user->rank->increment('monthly_wins');
            $winner->card->setBingo();
            $winner->prize->update(['is_claimed' => true]);
            $winner->user->rank->checkTitles();
        });
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
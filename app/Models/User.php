<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Wallet\Wallet;
use App\Models\Game\Game;
use App\Models\Game\Player;
use App\Models\Game\Winner;
use App\Models\Ranking\Rank;
use App\Models\Ranking\Title;

class User extends Authenticatable
{
    use Notifiable, HasUuids;

    protected $fillable = ['name', 'email', 'password', 'uuid'];
    
    protected $hidden = ['password', 'remember_token'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected static function booted()
    {
        static::created(function ($user) {
            $user->wallet()->create(['balance' => 0]);
            $user->rank()->create();
        });
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function createdGames()
    {
        return $this->hasMany(Game::class, 'creator_id');
    }

    public function playedGames()
    {
        return $this->hasManyThrough(Game::class, Player::class, 'user_id', 'id', 'id', 'game_id');
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function wins()
    {
        return $this->hasMany(Winner::class);
    }

    public function rank()
    {
        return $this->hasOne(Rank::class);
    }

    public function titles()
    {
        return $this->hasMany(Title::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
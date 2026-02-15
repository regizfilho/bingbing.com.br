<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Wallet\Wallet;
use App\Models\Game\Game;
use App\Models\Game\Player;
use App\Models\Game\Winner;
use App\Models\Ranking\Rank;
use App\Models\Ranking\Title;

class User extends Authenticatable
{
    use Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'uuid',
        'avatar_path',
        'phone_number',
        'document',
        'birth_date',
        'gender',
        'country',
        'state',
        'city',
        'language',
        'instagram',
        'bio',
        'role',
        'status',
        'ban_reason',
        'banned_at',
        'banned_by',
        'is_verified'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birth_date' => 'date',
        'is_verified' => 'boolean',
        'banned_at' => 'datetime',
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

    // Relacionamentos
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    public function rank()
    {
        return $this->hasOne(Rank::class);
    }
    public function titles()
    {
        return $this->hasMany(Title::class);
    }
    public function wins()
    {
        return $this->hasMany(Winner::class);
    }
    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function createdGames()
    {
        return $this->hasMany(Game::class, 'creator_id');
    }

    public function playedGames()
    {
        return $this->hasManyThrough(Game::class, Player::class, 'user_id', 'id', 'id', 'game_id');
    }

    public function bannedBy()
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function bannedUsers()
    {
        return $this->hasMany(User::class, 'banned_by');
    }

    // Helpers
    public function isBanned(): bool
    {
        return !is_null($this->banned_at);
    }

    public function ban(string $reason, ?int $adminId = null): void
    {
        $this->update([
            'ban_reason' => $reason,
            'banned_at' => now(),
            'banned_by' => $adminId ?? auth()->id(),
        ]);
    }

    public function unban(): void
    {
        $this->update([
            'ban_reason' => null,
            'banned_at' => null,
            'banned_by' => null,
        ]);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
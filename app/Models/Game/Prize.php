<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Prize extends Model
{
    use HasUuids;

    protected $fillable = ['uuid', 'game_id', 'name', 'description', 'position', 'is_claimed'];
    
    protected $casts = ['is_claimed' => 'boolean'];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function winner()
    {
        return $this->hasOne(Winner::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
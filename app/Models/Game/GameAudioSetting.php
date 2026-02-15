<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use App\Models\GameAudio;

class GameAudioSetting extends Model
{
    protected $fillable = [
        'game_id',
        'audio_category',
        'game_audio_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function audio()
    {
        return $this->belongsTo(GameAudio::class, 'game_audio_id');
    }
}
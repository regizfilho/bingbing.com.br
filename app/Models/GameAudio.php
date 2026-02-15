<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameAudio extends Model
{
    use HasFactory;

    protected $table = 'game_audios';

    protected $fillable = [
        'name', 'type', 'audio_type', 'file_path', 'tts_text', 'tts_voice',
        'tts_language', 'tts_rate', 'tts_pitch', 'tts_volume',
        'is_default', 'is_active', 'order',
    ];

    protected $casts = [
        'tts_rate' => 'float',
        'tts_pitch' => 'float',
        'tts_volume' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }
}
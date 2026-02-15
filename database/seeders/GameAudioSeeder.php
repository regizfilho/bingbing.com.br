<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameAudioSeeder extends Seeder
{
    public function run(): void
    {
        $audios = [
            // Sons do sistema (pré-carregados, assuma MP3s em storage/app/public/sounds/)
            [
                'name' => 'numero_sorteado',
                'type' => 'system',
                'audio_type' => 'mp3',
                'file_path' => 'sounds/numero.mp3',
                'is_default' => true,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'vencedor',
                'type' => 'system',
                'audio_type' => 'mp3',
                'file_path' => 'sounds/vencedor.mp3',
                'is_default' => true,
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'inicio_partida',
                'type' => 'system',
                'audio_type' => 'mp3',
                'file_path' => 'sounds/inicio.mp3',
                'is_default' => true,
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'fim_partida',
                'type' => 'system',
                'audio_type' => 'mp3',
                'file_path' => 'sounds/fim.mp3',
                'is_default' => true,
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'proxima_rodada',
                'type' => 'system',
                'audio_type' => 'mp3',
                'file_path' => 'sounds/proxima_rodada.mp3',
                'is_default' => true,
                'is_active' => true,
                'order' => 5,
            ],

            // Sons para display (player/display)
            [
                'name' => 'numero_display',
                'type' => 'player',
                'audio_type' => 'tts',
                'tts_text' => 'Número sorteado',
                'tts_voice' => 'Google Português',
                'tts_language' => 'pt-BR',
                'tts_rate' => 1.2,
                'tts_pitch' => 1.0,
                'tts_volume' => 0.8,
                'is_default' => true,
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'vencedor_display',
                'type' => 'player',
                'audio_type' => 'tts',
                'tts_text' => 'Novo vencedor',
                'tts_voice' => 'Google Português',
                'tts_language' => 'pt-BR',
                'tts_rate' => 1.0,
                'tts_pitch' => 0.9,
                'tts_volume' => 1.0,
                'is_default' => true,
                'is_active' => true,
                'order' => 2,
            ],
        ];

        foreach ($audios as $audio) {
            DB::table('game_audios')->updateOrInsert(
                ['type' => $audio['type'], 'name' => $audio['name']],
                $audio
            );
        }
    }
}
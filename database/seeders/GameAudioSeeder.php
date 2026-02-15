<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GameAudioSeeder extends Seeder
{
    public function run(): void
    {
        $audios = [
            // ── NÚMERO ───────────────────────────────────────────────
            [
                'name'              => 'Número Sorteado (Voz Feminina BR)',
                'type'              => 'number',
                'audio_type'        => 'tts',
                'tts_text'          => 'Número sorteado',
                'tts_voice'         => 'Google português do Brasil (feminino)',
                'tts_language'      => 'pt-BR',
                'tts_rate'          => 1.0,
                'tts_pitch'         => 1.0,
                'tts_volume'        => 0.9,
                'is_default'        => true,
                'is_active'         => true,
                'order'             => 1,
            ],
            [
                'name'              => 'Número Sorteado (Voz Masculina BR)',
                'type'              => 'number',
                'audio_type'        => 'tts',
                'tts_text'          => 'Número sorteado',
                'tts_voice'         => 'Google português do Brasil (masculino)',
                'tts_language'      => 'pt-BR',
                'tts_rate'          => 1.0,
                'tts_pitch'         => 1.0,
                'tts_volume'        => 0.9,
                'is_default'        => false,
                'is_active'         => true,
                'order'             => 2,
            ],
            [
                'name'              => 'Número Sorteado (MP3)',
                'type'              => 'number',
                'audio_type'        => 'mp3',
                'file_path'         => 'sounds/numero.mp3',
                'tts_text'          => null,
                'tts_voice'         => null,
                'tts_language'      => null,
                'tts_rate'          => null,
                'tts_pitch'         => null,
                'tts_volume'        => null,
                'is_default'        => false,
                'is_active'         => true,
                'order'             => 3,
            ],

            // ── VENCEDOR ─────────────────────────────────────────────
            [
                'name'              => 'Vencedor (Voz Feminina BR)',
                'type'              => 'winner',
                'audio_type'        => 'tts',
                'tts_text'          => 'Novo vencedor',
                'tts_voice'         => 'Google português do Brasil (feminino)',
                'tts_language'      => 'pt-BR',
                'tts_rate'          => 1.1,
                'tts_pitch'         => 1.0,
                'tts_volume'        => 1.0,
                'is_default'        => true,
                'is_active'         => true,
                'order'             => 10,
            ],
            [
                'name'              => 'Vencedor (Voz Masculina BR)',
                'type'              => 'winner',
                'audio_type'        => 'tts',
                'tts_text'          => 'Novo vencedor',
                'tts_voice'         => 'Google português do Brasil (masculino)',
                'tts_language'      => 'pt-BR',
                'tts_rate'          => 1.1,
                'tts_pitch'         => 1.0,
                'tts_volume'        => 1.0,
                'is_default'        => false,
                'is_active'         => true,
                'order'             => 11,
            ],
            [
                'name'              => 'Vencedor (MP3)',
                'type'              => 'winner',
                'audio_type'        => 'mp3',
                'file_path'         => 'sounds/vencedor.mp3',
                'tts_text'          => null,
                'tts_voice'         => null,
                'tts_language'      => null,
                'tts_rate'          => null,
                'tts_pitch'         => null,
                'tts_volume'        => null,
                'is_default'        => false,
                'is_active'         => true,
                'order'             => 12,
            ],
        ];

        foreach ($audios as $audio) {
            DB::table('game_audios')->updateOrInsert(
                ['name' => $audio['name']],
                $audio
            );
        }
    }
}
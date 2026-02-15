<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_audios', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: "Número sorteado", "Vencedor", "Início de rodada"
            $table->string('type'); // 'system' (pré-carregado) ou 'player' (para display)
            $table->enum('audio_type', ['mp3', 'tts']); // Tipo: MP3 ou Text-to-Speech
            $table->text('file_path')->nullable(); // Para MP3: caminho do arquivo
            $table->text('tts_text')->nullable(); // Para TTS: texto a falar
            $table->text('tts_voice')->nullable(); // Para TTS: nome da voz do browser
            $table->text('tts_language')->nullable()->default('pt-BR'); // Idioma TTS
            $table->float('tts_rate')->nullable()->default(1.0); // Velocidade TTS
            $table->float('tts_pitch')->nullable()->default(1.0); // Tom TTS
            $table->float('tts_volume')->nullable()->default(1.0); // Volume TTS
            $table->boolean('is_default')->default(false); // Som padrão para o tipo
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'name']); // Um som por tipo/nome
            $table->index(['type', 'is_default', 'is_active']); // Para buscas rápidas
        });

        // Seeders iniciais (rode manualmente ou crie seeder)
        // Exemplo: php artisan db:seed --class=GameAudioSeeder
    }

    public function down(): void
    {
        Schema::dropIfExists('game_audios');
    }
};
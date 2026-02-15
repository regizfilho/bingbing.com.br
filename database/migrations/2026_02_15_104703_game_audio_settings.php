<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_audio_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('audio_category'); // 'number', 'winner', 'system', etc
            $table->foreignId('game_audio_id')->constrained('game_audios')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            // Garante que cada game só tem um áudio por categoria
            $table->unique(['game_id', 'audio_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_audio_settings');
    }
};
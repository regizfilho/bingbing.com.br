<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_package_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('invite_code', 12)->unique();
            
            // Configurações de jogo (FALTANDO)
            $table->integer('card_size')->default(24);
            $table->integer('cards_per_player')->default(1);
            $table->integer('prizes_per_round')->default(1);
            $table->integer('max_rounds')->default(1);
            $table->integer('current_round')->default(1);
            
            // Flags booleanas (FALTANDO)
            $table->boolean('show_drawn_to_players')->default(false);
            $table->boolean('show_player_matches')->default(false);
            $table->boolean('auto_claim_prizes')->default(false);
            
            $table->enum('draw_mode', ['manual', 'automatic'])->default('manual');
            $table->integer('auto_draw_seconds')->nullable()->default(3);
            $table->enum('status', ['draft', 'waiting', 'active', 'paused', 'finished'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            $table->index(['invite_code', 'status']);
            $table->index('creator_id');
            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('games');
    }
};
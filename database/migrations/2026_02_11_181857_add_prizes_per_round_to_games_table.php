<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasColumn('games', 'cards_per_player')) {
                $table->integer('cards_per_player')->default(1)->after('card_size');
            }
            if (!Schema::hasColumn('games', 'prizes_per_round')) {
                $table->integer('prizes_per_round')->default(1)->after('max_rounds');
            }
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->index(['game_id', 'round_number', 'is_bingo'], 'cards_game_round_bingo_index');
            $table->index(['player_id', 'round_number'], 'cards_player_round_index');
        });

        Schema::table('draws', function (Blueprint $table) {
            // Remove constraint única antiga se existir
            try {
                $table->dropUnique(['game_id', 'number']);
            } catch (\Exception $e) {
                // Ignora se não existir
            }
            
            // Adiciona nova constraint com round_number
            $table->unique(['game_id', 'number', 'round_number'], 'draws_game_number_round_unique');
            $table->index(['game_id', 'round_number'], 'draws_game_round_index');
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->index(['game_id', 'round_number', 'prize_id'], 'winners_game_round_prize_index');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            if (Schema::hasColumn('games', 'cards_per_player')) {
                $table->dropColumn('cards_per_player');
            }
            if (Schema::hasColumn('games', 'prizes_per_round')) {
                $table->dropColumn('prizes_per_round');
            }
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex('cards_game_round_bingo_index');
            $table->dropIndex('cards_player_round_index');
        });

        Schema::table('draws', function (Blueprint $table) {
            $table->dropUnique('draws_game_number_round_unique');
            $table->dropIndex('draws_game_round_index');
            // Restaura constraint antiga
            $table->unique(['game_id', 'number']);
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->dropIndex('winners_game_round_prize_index');
        });
    }
};
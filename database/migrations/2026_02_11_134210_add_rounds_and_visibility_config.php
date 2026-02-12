<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Adiciona configurações de visibilidade, tamanho de cartela e features em game_packages
        Schema::table('game_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('game_packages', 'max_rounds')) {
                $table->integer('max_rounds')->default(1)->after('max_winners_per_prize');
            }

            if (!Schema::hasColumn('game_packages', 'allowed_card_sizes')) {
                $table->json('allowed_card_sizes')->nullable()->after('max_rounds'); // [9, 15, 24]
            }

            if (!Schema::hasColumn('game_packages', 'features')) {
                $table->json('features')->nullable()->after('can_customize_prizes');
            }
        });

        // Adiciona round_number em winners, draws, cards
        Schema::table('winners', function (Blueprint $table) {
            if (!Schema::hasColumn('winners', 'round_number')) {
                $table->integer('round_number')->default(1)->after('user_id');
            }
        });

        Schema::table('draws', function (Blueprint $table) {
            if (!Schema::hasColumn('draws', 'round_number')) {
                $table->integer('round_number')->default(1)->after('game_id');
            }
        });

        Schema::table('cards', function (Blueprint $table) {
            if (!Schema::hasColumn('cards', 'round_number')) {
                $table->integer('round_number')->default(1)->after('player_id');
            }
        });

        // Adiciona colunas em games
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasColumn('games', 'card_size')) {
                $table->integer('card_size')->default(24)->after('auto_draw_seconds'); // 9, 15, 24
            }
            if (!Schema::hasColumn('games', 'show_drawn_to_players')) {
                $table->boolean('show_drawn_to_players')->default(true)->after('card_size');
            }
            if (!Schema::hasColumn('games', 'show_player_matches')) {
                $table->boolean('show_player_matches')->default(true)->after('show_drawn_to_players');
            }
            if (!Schema::hasColumn('games', 'max_rounds')) {
                $table->integer('max_rounds')->default(1)->after('show_player_matches');
            }
            if (!Schema::hasColumn('games', 'current_round')) {
                $table->integer('current_round')->default(1)->after('max_rounds');
            }
        });
    }

    public function down()
    {
        // Remove colunas de games
        Schema::table('games', function (Blueprint $table) {
            $columns = [
                'card_size',
                'show_drawn_to_players',
                'show_player_matches',
                'max_rounds',
                'current_round'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('games', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Remove colunas de game_packages
        Schema::table('game_packages', function (Blueprint $table) {
            $columns = ['max_rounds', 'allowed_card_sizes', 'features'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('game_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Remove round_number de winners, draws e cards
        foreach (['winners', 'draws', 'cards'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'round_number')) {
                    $table->dropColumn('round_number');
                }
            });
        }
    }
};

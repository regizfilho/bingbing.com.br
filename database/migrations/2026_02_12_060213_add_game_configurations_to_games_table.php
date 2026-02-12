<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('games', function (Blueprint $table) {
            if (!Schema::hasColumn('games', 'card_size')) {
                $table->integer('card_size')->default(24)->after('invite_code');
            }
            if (!Schema::hasColumn('games', 'cards_per_player')) {
                $table->integer('cards_per_player')->default(1)->after('card_size');
            }
            if (!Schema::hasColumn('games', 'prizes_per_round')) {
                $table->integer('prizes_per_round')->default(1)->after('cards_per_player');
            }
            if (!Schema::hasColumn('games', 'max_rounds')) {
                $table->integer('max_rounds')->default(1)->after('prizes_per_round');
            }
            if (!Schema::hasColumn('games', 'current_round')) {
                $table->integer('current_round')->default(1)->after('max_rounds');
            }
            if (!Schema::hasColumn('games', 'show_drawn_to_players')) {
                $table->boolean('show_drawn_to_players')->default(false)->after('current_round');
            }
            if (!Schema::hasColumn('games', 'show_player_matches')) {
                $table->boolean('show_player_matches')->default(false)->after('show_drawn_to_players');
            }
            if (!Schema::hasColumn('games', 'auto_claim_prizes')) {
                $table->boolean('auto_claim_prizes')->default(false)->after('show_player_matches');
            }
        });
    }

    public function down()
    {
        Schema::table('games', function (Blueprint $table) {
            $columns = [
                'card_size',
                'cards_per_player',
                'prizes_per_round',
                'max_rounds',
                'current_round',
                'show_drawn_to_players',
                'show_player_matches',
                'auto_claim_prizes'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('games', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
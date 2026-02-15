<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_packages', function (Blueprint $table) {

            if (!Schema::hasColumn('game_packages', 'max_rounds')) {
                $table->integer('max_rounds')
                    ->default(1)
                    ->after('max_players');
            }

            if (!Schema::hasColumn('game_packages', 'cards_per_player')) {
                $table->integer('cards_per_player')
                    ->default(1)
                    ->after('max_cards_per_player');
            }

            if (!Schema::hasColumn('game_packages', 'allowed_card_sizes')) {
                $table->json('allowed_card_sizes')
                    ->nullable()
                    ->after('cards_per_player');
            }

            if (!Schema::hasColumn('game_packages', 'daily_limit')) {
                $table->integer('daily_limit')
                    ->nullable()
                    ->after('allowed_card_sizes');
            }

            if (!Schema::hasColumn('game_packages', 'monthly_limit')) {
                $table->integer('monthly_limit')
                    ->nullable()
                    ->after('daily_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('game_packages', function (Blueprint $table) {

            $columns = [
                'max_rounds',
                'cards_per_player',
                'allowed_card_sizes',
                'daily_limit',
                'monthly_limit',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('game_packages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

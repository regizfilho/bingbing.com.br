<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            if (!Schema::hasColumn('cards', 'game_id')) {
                $table->foreignId('game_id')->nullable()->after('id')->constrained('games')->cascadeOnDelete();
            }
        });
        
        // Popula game_id baseado no player_id
        \DB::statement('
            UPDATE cards 
            SET game_id = (
                SELECT game_id 
                FROM players 
                WHERE players.id = cards.player_id
            )
            WHERE game_id IS NULL
        ');
        
        // Remove nullable após popular
        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            if (Schema::hasColumn('cards', 'game_id')) {
                $table->dropForeign(['game_id']);
                $table->dropColumn('game_id');
            }
        });
    }
};
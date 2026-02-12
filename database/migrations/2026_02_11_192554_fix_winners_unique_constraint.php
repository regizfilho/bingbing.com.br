<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            // Remove constraint única antiga em prize_id
            try {
                $table->dropUnique(['prize_id']);
            } catch (\Exception $e) {
                // Ignora se não existir
            }
            
            // Adiciona constraint com round_number
            $table->unique(['prize_id', 'round_number'], 'winners_prize_round_unique');
        });
    }

    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropUnique('winners_prize_round_unique');
            $table->unique(['prize_id']);
        });
    }
};
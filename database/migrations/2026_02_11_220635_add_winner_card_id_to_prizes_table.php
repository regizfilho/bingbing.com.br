<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prizes', function (Blueprint $coluna) {
            // Adiciona a coluna como um ID estrangeiro opcional (nullable)
            $coluna->foreignId('winner_card_id')->nullable()->constrained('cards')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prizes', function (Blueprint $coluna) {
            $coluna->dropForeign(['winner_card_id']);
            $coluna->dropColumn('winner_card_id');
        });
    }
};
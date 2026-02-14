<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // ID do pacote comprado (quando type = credit e é compra de créditos)
            $table->foreignId('package_id')->nullable()->after('wallet_id')->constrained('packages')->nullOnDelete();
            
            // ID do gift card resgatado (quando type = credit e é resgate de gift card)
            $table->foreignId('gift_card_id')->nullable()->after('package_id')->constrained('gift_cards')->nullOnDelete();
            
            // Adicionar índices para performance
            $table->index('package_id');
            $table->index('gift_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['gift_card_id']);
            $table->dropIndex(['package_id']);
            $table->dropIndex(['gift_card_id']);
            $table->dropColumn(['package_id', 'gift_card_id']);
        });
    }
};
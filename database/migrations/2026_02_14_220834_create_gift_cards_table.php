<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 20)->unique(); // Código do gift card
            
            // Valores
            $table->decimal('credit_value', 10, 2); // Valor em créditos
            $table->decimal('price_brl', 10, 2)->nullable(); // Custo em reais (null se gerado pelo admin)
            
            // Origem
            $table->enum('source', ['admin', 'purchase'])->default('purchase');
            $table->text('description')->nullable(); // Motivo da criação
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Status e uso
            $table->enum('status', ['active', 'redeemed', 'expired'])->default('active');
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['code', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
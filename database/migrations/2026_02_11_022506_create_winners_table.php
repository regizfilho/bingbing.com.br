<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('won_at');
            $table->timestamps();
            
            $table->unique('prize_id');
            $table->index(['game_id', 'user_id']);
            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('winners');
    }
};
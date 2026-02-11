<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamps();
            
            $table->unique(['game_id', 'user_id']);
            $table->index('game_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('players');
    }
};
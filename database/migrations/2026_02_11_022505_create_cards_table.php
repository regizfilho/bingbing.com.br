<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->json('numbers');
            $table->json('marked')->nullable();
            $table->boolean('is_bingo')->default(false);
            $table->timestamp('bingo_at')->nullable();
            $table->timestamps();
            
            $table->index(['player_id', 'is_bingo']);
            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cards');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('game_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('cost_credits', 10, 2);
            $table->integer('max_players');
            $table->integer('max_cards_per_player')->default(1);
            $table->integer('max_winners_per_prize')->default(1);
            $table->boolean('can_customize_prizes')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('game_packages');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('number');
            $table->integer('sequence');
            $table->timestamp('drawn_at');
            $table->timestamps();
            
            $table->unique(['game_id', 'number']);
            $table->index(['game_id', 'sequence']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('draws');
    }
};
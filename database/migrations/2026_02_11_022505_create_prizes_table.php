<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('prizes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_claimed')->default(false);
            $table->timestamps();
            
            $table->index(['game_id', 'position']);
            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('prizes');
    }
};
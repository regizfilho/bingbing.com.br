<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['beginner', 'experienced', 'master', 'legend']);
            $table->timestamp('earned_at');
            $table->timestamps();
            
            $table->unique(['user_id', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('titles');
    }
};
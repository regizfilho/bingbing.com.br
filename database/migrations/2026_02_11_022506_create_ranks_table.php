<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('total_wins')->default(0);
            $table->integer('weekly_wins')->default(0);
            $table->integer('monthly_wins')->default(0);
            $table->integer('total_games')->default(0);
            $table->timestamp('weekly_reset_at')->nullable();
            $table->timestamp('monthly_reset_at')->nullable();
            $table->timestamps();
            
            $table->unique('user_id');
            $table->index(['total_wins', 'weekly_wins', 'monthly_wins']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ranks');
    }
};
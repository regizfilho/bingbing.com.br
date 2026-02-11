<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit', 'refund']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description');
            $table->morphs('transactionable');
            $table->enum('status', ['pending', 'completed', 'refunded'])->default('completed');
            $table->timestamp('refund_requested_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'created_at']);
            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
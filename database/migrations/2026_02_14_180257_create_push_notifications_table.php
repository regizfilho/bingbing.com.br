<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title', 100);
            $table->text('body');
            $table->string('icon', 500)->nullable();
            $table->string('badge', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->json('data')->nullable();
            $table->enum('target_type', ['all', 'user'])->default('all');
            $table->json('target_filters')->nullable();
            $table->integer('total_sent')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('created_by');
            $table->index('target_type');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
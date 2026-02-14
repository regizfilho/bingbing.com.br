<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_clicks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('push_notification_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('push_subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->index(['push_notification_id', 'user_id']);
            $table->index('clicked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_clicks');
    }
};
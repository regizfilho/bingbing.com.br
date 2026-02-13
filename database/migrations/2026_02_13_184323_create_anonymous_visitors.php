<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique()->index();
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->string('device_type')->nullable(); // mobile, desktop, tablet
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            
            // Origem
            $table->string('referrer_url')->nullable();
            $table->string('landing_page')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            
            // Atividade
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->integer('page_views')->default(1);
            $table->integer('duration_seconds')->nullable();
            $table->boolean('converted_to_user')->default(false)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['last_seen_at', 'converted_to_user']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymous_visitors');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // IPs Autorizados
        Schema::create('whitelist_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Logs de Bloqueio
        Schema::create('firewall_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip');
            $table->string('url');
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whitelist_ips');
        Schema::dropIfExists('firewall_logs');
    }
};
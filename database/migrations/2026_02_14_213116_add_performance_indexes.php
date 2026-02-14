<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_notification_clicks', function (Blueprint $table) {
            // Índice composto para buscar clicks duplicados rapidamente
            $table->index(['push_notification_id', 'user_id', 'clicked_at'], 'idx_notification_user_time');
            
            // Índice para buscar clicks de uma notificação específica
            $table->index(['push_notification_id', 'clicked_at'], 'idx_notification_time');
        });
        
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Índice para buscar subscriptions ativas por usuário
            $table->index(['user_id', 'is_active'], 'idx_user_active');
            
            // Índice para buscar subscriptions ativas globalmente
            $table->index(['is_active', 'last_used_at'], 'idx_active_last_used');
        });
        
        Schema::table('push_notifications', function (Blueprint $table) {
            // Índice para buscar por status
            $table->index(['status', 'created_at'], 'idx_status_created');
            
            // Índice para buscar por target_type
            $table->index(['target_type', 'status'], 'idx_target_status');
        });
    }

    public function down(): void
    {
        Schema::table('push_notification_clicks', function (Blueprint $table) {
            $table->dropIndex('idx_notification_user_time');
            $table->dropIndex('idx_notification_time');
        });
        
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_user_active');
            $table->dropIndex('idx_active_last_used');
        });
        
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_status_created');
            $table->dropIndex('idx_target_status');
        });
    }
};
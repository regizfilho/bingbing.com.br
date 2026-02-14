<?php

namespace App\Console\Commands;

use App\Models\Notification\PushSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupInactiveSubscriptions extends Command
{
    protected $signature = 'notifications:cleanup-subscriptions';
    protected $description = 'Remove subscriptions inativas há mais de 30 dias';

    public function handle(): int
    {
        $this->info('🧹 Iniciando limpeza de subscriptions inativas...');

        // Remover subscriptions inativas há mais de 30 dias
        $deletedCount = PushSubscription::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $this->info("✅ Removidas {$deletedCount} subscriptions inativas.");
        Log::info('Cleanup de subscriptions inativas', ['deleted' => $deletedCount]);

        // Desativar subscriptions que não foram usadas há mais de 90 dias
        $deactivatedCount = PushSubscription::where('is_active', true)
            ->where(function($q) {
                $q->whereNull('last_used_at')
                  ->orWhere('last_used_at', '<', now()->subDays(90));
            })
            ->update(['is_active' => false]);

        $this->info("⚠️  Desativadas {$deactivatedCount} subscriptions sem uso há mais de 90 dias.");
        Log::info('Subscriptions desativadas por inatividade', ['deactivated' => $deactivatedCount]);

        // Estatísticas finais
        $activeCount = PushSubscription::where('is_active', true)->count();
        $totalCount = PushSubscription::count();

        $this->newLine();
        $this->info("📊 Estatísticas:");
        $this->line("   Ativas: {$activeCount}");
        $this->line("   Total: {$totalCount}");
        $this->newLine();
        $this->info('✨ Limpeza concluída com sucesso!');

        return Command::SUCCESS;
    }
}
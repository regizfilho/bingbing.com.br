<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserSession;
use App\Models\AnonymousVisitor;

class CleanupAnalytics extends Command
{
    protected $signature = 'analytics:cleanup
                          {--days=30 : Number of days to keep data}';

    protected $description = 'Limpa dados antigos de analytics (sessões e visitantes)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $this->info("Limpando dados de analytics com mais de {$days} dias...");

        // Finaliza sessões inativas
        $this->info('Finalizando sessões inativas...');
        UserSession::endInactiveSessions();
        $this->info('✓ Sessões inativas finalizadas');

        // Remove visitantes antigos
        $this->info("Removendo visitantes anônimos com mais de {$days} dias...");
        $deletedVisitors = AnonymousVisitor::cleanup();
        $this->info("✓ {$deletedVisitors} visitantes antigos removidos");

        // Remove sessões antigas
        $this->info("Removendo sessões com mais de {$days} dias...");
        $deletedSessions = UserSession::where('ended_at', '<', now()->subDays($days))
            ->delete();
        $this->info("✓ {$deletedSessions} sessões antigas removidas");

        $this->info('');
        $this->info('✓ Limpeza concluída com sucesso!');

        return self::SUCCESS;
    }
}
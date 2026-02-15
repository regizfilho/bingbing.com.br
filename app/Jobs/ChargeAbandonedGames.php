<?php

namespace App\Jobs;

use App\Models\Game\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeAbandonedGames implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function handle(): void
    {
        Game::whereIn('status', ['active', 'waiting', 'draft'])
            ->where('created_at', '<', now()->subHours(24))
            ->orderBy('id')
            ->chunkById(100, function ($games) {

                foreach ($games as $game) {

                    DB::transaction(function () use ($game) {

                        // 🔒 Lock para evitar concorrência
                        $lockedGame = Game::where('id', $game->id)
                            ->lockForUpdate()
                            ->first();

                        // 🛑 Se já foi finalizado por outro worker
                        if (!in_array($lockedGame->status, ['active', 'waiting', 'draft'])) {
                            return;
                        }

                        $lockedGame->update([
                            'status' => 'finished',
                            'finished_at' => now(),
                        ]);

                        Log::info('Jogo finalizado por abandono', [
                            'game_id' => $lockedGame->id,
                            'creator_id' => $lockedGame->creator_id,
                        ]);
                    });
                }
            });
    }
}

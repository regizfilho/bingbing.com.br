<?php

namespace App\Jobs;

use App\Models\Game\Game;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ChargeAbandonedGames implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $abandonedGames = Game::whereIn('status', ['active', 'waiting', 'draft'])
            ->where('created_at', '<', now()->subHours(24))
            ->with(['creator.wallet', 'package'])
            ->get();

        foreach ($abandonedGames as $game) {
            $game->update(['status' => 'finished', 'finished_at' => now()]);
        }
    }
}
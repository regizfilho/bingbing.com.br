<?php

namespace App\Events;

use App\Models\Game\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): Channel
    {

        return new Channel('game.' . $this->game->uuid);
    }

    public function broadcastAs(): string
    {
        return 'GameUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->game->uuid,
            'status' => $this->game->status,
            'current_round' => $this->game->current_round,
            'timestamp' => now()->timestamp,
        ];
    }
}
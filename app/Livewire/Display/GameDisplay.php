<?php

namespace App\Livewire\Display;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Game\Game;

#[Layout('layouts.display')]
class GameDisplay extends Component
{
    public string $gameUuid;
    public Game $game;

    public array $currentDraws = [];
    public array $drawnNumbers = [];
    public array $roundWinners = [];
    public array $prizes = [];

    public function mount(string $uuid): void
    {
        $this->gameUuid = $uuid;

        $this->game = Game::where('uuid', $uuid)
            ->with(['draws', 'winners.user', 'prizes.winner.user', 'players', 'creator'])
            ->firstOrFail();

        $this->loadGameData();
    }

    public function getListeners(): array
    {
        return [
            "echo:game.{$this->gameUuid},.GameUpdated" => 'handleGameUpdate',
        ];
    }


    public function handleGameUpdate(): void
    {
        $this->game->refresh();
        $this->loadGameData();
    }

    private function loadGameData(): void
    {
        $round = $this->game->current_round;

        $this->currentDraws = $this->game->draws
            ->where('round_number', $round)
            ->sortByDesc('created_at')
            ->take(8)
            ->values()
            ->toArray();

        $this->drawnNumbers = $this->game->draws
            ->where('round_number', $round)
            ->pluck('number')
            ->toArray();

        $this->roundWinners = $this->game->winners()
            ->with('user')
            ->where('round_number', $round)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($w) => ['name' => $w->user->name])
            ->toArray();

        $this->prizes = $this->game->prizes
            ->map(function ($prize) use ($round) {
                $winner = $prize->winner?->where('round_number', $round)->first();

                return [
                    'id' => $prize->id,
                    'name' => $prize->name,
                    'position' => $prize->position,
                    'winner' => $winner?->user->name,
                ];
            })
            ->toArray();
    }

    public function render()
    {
        return view('livewire.display.game-display');
    }
}

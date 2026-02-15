<?php

namespace App\Models\Game;

use App\Models\GameAudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Game extends Model
{
    use HasUuids;

    // app/Models/Game/Game.php

protected $fillable = [
    'uuid',
    'creator_id',
    'game_package_id',
    'name',
    'invite_code',
    'draw_mode',
    'auto_draw_seconds',
    'status',
    'started_at',
    'finished_at',
    // ADICIONE ESTES:
    'card_size',
    'cards_per_player',
    'prizes_per_round',
    'max_rounds',
    'current_round',
    'show_drawn_to_players',
    'show_player_matches',
    'auto_claim_prizes',
];

protected $casts = [
    'show_drawn_to_players' => 'boolean',
    'show_player_matches' => 'boolean',
    'auto_claim_prizes' => 'boolean',
    'started_at' => 'datetime',
    'finished_at' => 'datetime',
];

    protected $attributes = [
        'current_round' => 1,
        'status' => 'waiting',
        'card_size' => 24,
        'max_rounds' => 1,
        'draw_mode' => 'manual',
        'auto_draw_seconds' => 3,  // Mudei de 10 para 3
        'show_drawn_to_players' => true,
        'show_player_matches' => true,
        'cards_per_player' => 1,
        'prizes_per_round' => 1,
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    protected static function booted()
    {
        static::creating(function ($game) {
            if (! $game->invite_code) {
                $game->invite_code = strtoupper(Str::random(12));
            }
        });
    }

    // Relacionamentos
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function package()
    {
        return $this->belongsTo(GamePackage::class, 'game_package_id');
    }

    public function prizes()
    {
        return $this->hasMany(Prize::class)->orderBy('position');
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function draws()
    {
        return $this->hasMany(Draw::class)->orderBy('sequence');
    }

    public function winners()
    {
        return $this->hasMany(Winner::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }

    public function canJoin(): bool
    {
        return $this->status === 'waiting' &&
            $this->players()->count() < $this->package->max_players;
    }

    public function generateCardNumbers(): array
    {
        $numbers = [];
        $cardSize = $this->card_size ?? 24;

        while (count($numbers) < $cardSize) {
            $num = rand(1, 75);
            if (! in_array($num, $numbers)) {
                $numbers[] = $num;
            }
        }
        sort($numbers);

        return $numbers;
    }

    /**
     * CORREÇÃO: Garante a rodada correta buscando direto do banco se necessário
     */
    public function generateCardsForCurrentRound(): void
    {
        // Força sincronização com o banco de dados
        $this->refresh();

        $currentRound = (int) $this->current_round;
        $cardsPerPlayer = (int) ($this->cards_per_player ?? 1);

        Log::info("GERANDO CARTELAS: Jogo {$this->id} para Rodada {$currentRound}");

        foreach ($this->players as $player) {
            // Limpa lixo da rodada atual para evitar duplicatas por cliques repetidos
            Card::where('player_id', $player->id)
                ->where('round_number', $currentRound)
                ->delete();

            for ($i = 0; $i < $cardsPerPlayer; $i++) {
                $this->cards()->create([
                    'uuid' => (string) Str::uuid(),
                    'player_id' => $player->id,
                    'round_number' => $currentRound,
                    'numbers' => $this->generateCardNumbers(),
                    'marked' => [],
                    'is_bingo' => false,
                ]);
            }
        }
    }

    public function drawNumber(): ?Draw
    {
        if ($this->status !== 'active') {
            return null;
        }

        $drawnNumbers = $this->getCurrentRoundDrawnNumbers();
        if (count($drawnNumbers) >= 75) {
            return null;
        }

        $available = array_diff(range(1, 75), $drawnNumbers);
        if (empty($available)) {
            return null;
        }

        $number = collect($available)->random();
        $sequence = $this->draws()->where('round_number', $this->current_round)->max('sequence') ?? 0;

        return $this->draws()->create([
            'game_id' => $this->id,
            'number' => $number,
            'sequence' => $sequence + 1,
            'round_number' => $this->current_round,
            'drawn_at' => now(),
        ]);
    }

    public function checkWinningCards(): Collection
    {
        $drawnNumbers = $this->getCurrentRoundDrawnNumbers();

        return $this->cards()
            ->where('round_number', $this->current_round)
            ->get()
            ->filter(function ($card) use ($drawnNumbers) {
                return $card->checkBingo($drawnNumbers);
            });
    }

    /**
     * AJUSTE: Garante que a rodada seja persistida antes de gerar cartelas
     */
    public function startNextRound(): bool
    {
        if (! $this->canStartNextRound()) {
            return false;
        }

        // Persistência imediata do incremento
        $this->increment('current_round');

        // Refresh para carregar o novo valor e as relações
        $this->refresh();

        // Agora gera as cartelas com a rodada já atualizada no banco
        $this->generateCardsForCurrentRound();

        return true;
    }

    public function canStartNextRound(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->current_round >= $this->max_rounds) {
            return false;
        }
        if ($this->players()->count() === 0) {
            return false;
        }

        if ($this->prizes()->count() === 0) {
            return true;
        }

        $claimedInRound = $this->winners()
            ->where('round_number', $this->current_round)
            ->distinct('prize_id')
            ->count('prize_id');

        return $claimedInRound >= $this->prizes_per_round;
    }

    public function getNextAvailablePrize()
    {
        return $this->prizes()
            ->where('is_claimed', false)
            ->orderBy('position')
            ->first();
    }

    public function hasAvailablePrizes(): bool
    {
        return $this->prizes()->where('is_claimed', false)->exists();
    }

    public function getGameRanking(): array
    {
        $winners = $this->winners()->with(['user', 'prize'])->orderBy('won_at')->get();
        $ranking = [];

        foreach ($winners as $winner) {
            $userId = $winner->user_id;
            if (! isset($ranking[$userId])) {
                $ranking[$userId] = [
                    'user' => $winner->user,
                    'wins' => 0,
                    'prizes' => [],
                    'rounds' => [],
                ];
            }
            $ranking[$userId]['wins']++;
            $ranking[$userId]['prizes'][] = $winner->prize->name;
            $ranking[$userId]['rounds'][] = $winner->round_number;
        }

        usort($ranking, fn ($a, $b) => $b['wins'] <=> $a['wins']);

        return array_values($ranking);
    }

    public function getActivePlayersCount(): int
    {
        return $this->players()->count();
    }

    public function getCurrentRoundDrawsCount(): int
    {
        return $this->draws()->where('round_number', $this->current_round)->count();
    }

    public function getCurrentRoundDrawnNumbers(): array
    {
        return $this->draws()->where('round_number', $this->current_round)->pluck('number')->toArray();
    }

    public function isFull(): bool
    {
        return $this->players()->count() >= $this->package->max_players;
    }

    public function getSpotsLeft(): int
    {
        return max(0, $this->package->max_players - $this->players()->count());
    }

    public function willRefund(): bool
    {
        return $this->status === 'finished' && ($this->players()->count() === 0 || $this->winners()->count() === 0);
    }

    public function getPackageCost(): int
    {
        return $this->package->cost_credits ?? 0;
    }

    public function isFree(): bool
    {
        return $this->package->is_free ?? true;
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    
    public function audioSettings()
    {
        return $this->hasMany(GameAudioSetting::class);
    }

    // Helper methods para facilitar o uso
    public function getAudioForCategory(string $category): ?GameAudio
    {
        $setting = $this->audioSettings()
            ->where('audio_category', $category)
            ->where('is_enabled', true)
            ->with('audio')
            ->first();

        return $setting?->audio;
    }

    public function setAudioForCategory(string $category, int $audioId, bool $enabled = true): void
    {
        $this->audioSettings()->updateOrCreate(
            ['audio_category' => $category],
            [
                'game_audio_id' => $audioId,
                'is_enabled' => $enabled,
            ]
        );
    }

    public function hasCustomAudio(): bool
    {
        return $this->audioSettings()->where('is_enabled', true)->exists();
    }

}

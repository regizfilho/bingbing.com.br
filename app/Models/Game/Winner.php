<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

class Winner extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 
        'game_id', 
        'round_number', 
        'prize_id', 
        'card_id', 
        'user_id', 
        'won_at'
    ];

    protected $casts = [
        'won_at' => 'datetime'
    ];

    /**
     * Define os campos que devem ser tratados como UUID.
     */
    public function uniqueIds()
    {
        return ['uuid'];
    }

    /**
     * O "booted" do Model Winner automatiza ações após o Bingo.
     */
    protected static function booted()
    {
        static::created(function ($winner) {
            // 1. Gerencia o Rank do Usuário (Busca ou Cria)
            $rank = $winner->user->rank ?? $winner->user->rank()->create([
                'total_wins' => 0,
                'weekly_wins' => 0,
                'monthly_wins' => 0,
            ]);

            // 2. Incrementa estatísticas de vitória
            $rank->increment('total_wins');
            $rank->increment('weekly_wins');
            $rank->increment('monthly_wins');

            // 3. Marca a cartela como Bingo (executa lógica interna da cartela)
            if ($winner->card) {
                $winner->card->setBingo();
            }

            // 4. ATUALIZAÇÃO IMPORTANTE: Só atualiza o prêmio se ele existir.
            // Isso permite que o "Bingo de Mérito/Honra" (prize_id = null) funcione.
            if ($winner->prize) {
                $winner->prize->update(['is_claimed' => true]);
            }

            // 5. Verifica se o usuário ganhou novos títulos/badges após a vitória
            $rank->checkTitles();
        });
    }

    /**
     * Relacionamento com o Jogo.
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Relacionamento com o Prêmio (pode ser nulo para registro de mérito).
     */
    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }

    /**
     * Relacionamento com a Cartela vencedora.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Relacionamento com o Usuário vencedor.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Define o UUID para busca em rotas.
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}
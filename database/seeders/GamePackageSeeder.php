<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Game\GamePackage;

class GamePackageSeeder extends Seeder
{
    public function run()
    {
        GamePackage::create([
            'name' => 'Free',
            'slug' => 'free',
            'cost_credits' => 0,
            'max_players' => 10,
            'max_cards_per_player' => 1,
            'max_winners_per_prize' => 1,
            'max_rounds' => 1,
            'allowed_card_sizes' => [24],
            'can_customize_prizes' => false,
            'features' => [
                '1 rodada única',
                'Até 10 jogadores',
                '1 cartela por jogador',
                'Cartela de 24 números'
            ],
            'is_free' => true,
            'is_active' => true,
            'order' => 1,
        ]);

        GamePackage::create([
            'name' => 'Básico',
            'slug' => 'basic',
            'cost_credits' => 10,
            'max_players' => 30,
            'max_cards_per_player' => 2,
            'max_winners_per_prize' => 2,
            'max_rounds' => 3,
            'allowed_card_sizes' => [15, 24],
            'can_customize_prizes' => true,
            'features' => [
                '3 rodadas por partida',
                'Até 30 jogadores',
                '2 cartelas por jogador',
                'Cartelas de 15 ou 24 números',
                'Prêmios personalizados',
                'Controle de visibilidade'
            ],
            'is_active' => true,
            'order' => 2,
        ]);

        GamePackage::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'cost_credits' => 25,
            'max_players' => 100,
            'max_cards_per_player' => 5,
            'max_winners_per_prize' => 3,
            'max_rounds' => 999,
            'allowed_card_sizes' => [9, 15, 24],
            'can_customize_prizes' => true,
            'features' => [
                'Rodadas ilimitadas',
                'Até 100 jogadores',
                '5 cartelas por jogador',
                'Todos os tamanhos de cartela',
                'Prêmios personalizados',
                'Modo automático',
                'Controle total de visibilidade',
                'Estatísticas avançadas'
            ],
            'is_active' => true,
            'order' => 3,
        ]);
    }
}
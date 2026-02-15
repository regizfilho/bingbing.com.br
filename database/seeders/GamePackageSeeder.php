<?php

namespace Database\Seeders;

use App\Models\Game\GamePackage;
use Illuminate\Database\Seeder;

class GamePackageSeeder extends Seeder
{
    public function run(): void
    {
        GamePackage::truncate();

        GamePackage::create([
            'name' => 'Gratuito',
            'slug' => 'free',
            'cost_credits' => 0,
            'is_free' => true,
            'max_players' => 10,
            'max_rounds' => 1,
            'max_cards_per_player' => 1,
            'cards_per_player' => 1,
            'allowed_card_sizes' => [9, 15, 24],
            'features' => [
                '2 salas por dia',
                '15 salas por mês',
                'Até 10 jogadores',
                '1 rodada',
                '1 cartela por jogador',
                'Sorteio manual',
                'Display público básico',
            ],
            'daily_limit' => 2,
            'monthly_limit' => 15,
            'is_active' => true,
        ]);

        GamePackage::create([
            'name' => 'Básico',
            'slug' => 'basic',
            'cost_credits' => 10,
            'is_free' => false,
            'max_players' => 30,
            'max_rounds' => 3,
            'max_cards_per_player' => 3,
            'cards_per_player' => 1,
            'allowed_card_sizes' => [9, 15, 24],
            'features' => [
                'Até 30 jogadores',
                '3 rodadas',
                '3 cartelas por jogador',
                'Sorteio automático',
                'Auto claim',
            ],
            'daily_limit' => 10,
            'monthly_limit' => 100,
            'is_active' => true,
        ]);

        GamePackage::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'cost_credits' => 25,
            'is_free' => false,
            'max_players' => 100,
            'max_rounds' => 6,
            'max_cards_per_player' => 6,
            'cards_per_player' => 2,
            'allowed_card_sizes' => [9, 15, 24],
            'features' => [
                'Até 100 jogadores',
                '6 rodadas',
                '6 cartelas por jogador',
                'Sorteio automático avançado',
                'Auto claim',
                'Sem branding',
            ],
            'daily_limit' => 30,
            'monthly_limit' => 300,
            'is_active' => true,
        ]);

        GamePackage::create([
            'name' => 'VIP',
            'slug' => 'vip',
            'cost_credits' => 50,
            'is_free' => false,
            'max_players' => 9999, // Valor alto para indicar ilimitado
            'max_rounds' => 10,
            'max_cards_per_player' => 10,
            'cards_per_player' => 3,
            'allowed_card_sizes' => [9, 15, 24],
            'features' => [
                'Jogadores ilimitados',
                '10 rodadas',
                '10 cartelas por jogador',
                'Branding personalizado',
                'Suporte prioritário',
                'Analytics avançado',
            ],
            'daily_limit' => 9999, // Valor alto para indicar ilimitado
            'monthly_limit' => 9999, // Valor alto para indicar ilimitado
            'is_active' => true,
        ]);
    }
}
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
            'can_customize_prizes' => false,
            'features' => ['Até 10 jogadores', '1 cartela por jogador'],
            'is_free' => true,
            'is_active' => true,
            'order' => 1,
        ]);

        GamePackage::create([
            'name' => 'Básico',
            'slug' => 'basic',
            'cost_credits' => 5,
            'max_players' => 30,
            'max_cards_per_player' => 2,
            'max_winners_per_prize' => 2,
            'can_customize_prizes' => true,
            'features' => ['Até 30 jogadores', '2 cartelas por jogador', 'Prêmios personalizados'],
            'is_active' => true,
            'order' => 2,
        ]);

        GamePackage::create([
            'name' => 'Premium',
            'slug' => 'premium',
            'cost_credits' => 10,
            'max_players' => 100,
            'max_cards_per_player' => 5,
            'max_winners_per_prize' => 3,
            'can_customize_prizes' => true,
            'features' => ['Até 100 jogadores', '5 cartelas por jogador', 'Prêmios personalizados', 'Modo automático'],
            'is_active' => true,
            'order' => 3,
        ]);
    }
}
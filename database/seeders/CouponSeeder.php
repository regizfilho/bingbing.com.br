<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Criar Cupons
        |--------------------------------------------------------------------------
        */

        $welcome = Coupon::create([
            'code' => 'WELCOME10',
            'description' => 'Cupom de boas-vindas 10%',
            'type' => 'percent',
            'value' => 10,
            'min_order_value' => 100,
            'expires_at' => now()->addMonths(3),
            'usage_limit' => 100,
            'per_user_limit' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $vip = Coupon::create([
            'code' => 'VIP50',
            'description' => 'Desconto fixo VIP',
            'type' => 'fixed',
            'value' => 50,
            'min_order_value' => 300,
            'expires_at' => now()->addMonth(),
            'usage_limit' => 20,
            'per_user_limit' => 2,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $expired = Coupon::create([
            'code' => 'EXPIRED20',
            'description' => 'Cupom expirado para testes',
            'type' => 'percent',
            'value' => 20,
            'min_order_value' => 50,
            'expires_at' => now()->subDays(5),
            'usage_limit' => 50,
            'per_user_limit' => 1,
            'used_count' => 0,
            'is_active' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Gerar Histórico Realista
        |--------------------------------------------------------------------------
        */

        $users = User::inRandomOrder()->take(10)->get();

        foreach ($users as $user) {

            // Simular valor do pedido
            $orderValue = rand(120, 1000);

            // Garantir que atende o mínimo
            if ($orderValue < $welcome->min_order_value) {
                $orderValue = $welcome->min_order_value + rand(10, 200);
            }

            // Calcular desconto
            $discount = $welcome->type === 'percent'
                ? ($orderValue * $welcome->value) / 100
                : $welcome->value;

            $welcome->users()->attach($user->id, [
                'used_at' => Carbon::now()->subDays(rand(1, 30)),
                'order_value' => $orderValue,
                'discount_amount' => $discount,
                'ip_address' => fake()->ipv4(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $welcome->increment('used_count');
        }

        /*
        |--------------------------------------------------------------------------
        | Simular usos VIP
        |--------------------------------------------------------------------------
        */

        $vipUsers = User::inRandomOrder()->take(5)->get();

        foreach ($vipUsers as $user) {

            $orderValue = rand(350, 2000);
            $discount = $vip->value;

            $vip->users()->attach($user->id, [
                'used_at' => Carbon::now()->subDays(rand(1, 20)),
                'order_value' => $orderValue,
                'discount_amount' => $discount,
                'ip_address' => fake()->ipv4(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $vip->increment('used_count');
        }
    }
}

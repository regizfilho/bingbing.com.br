<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wallet\Package;

class PackageSeeder extends Seeder
{
    public function run()
    {
        Package::create([
            'name' => 'Starter',
            'credits' => 10,
            'price_brl' => 5.00,
            'is_active' => true,
            'order' => 1
        ]);

        Package::create([
            'name' => 'Popular',
            'credits' => 50,
            'price_brl' => 20.00,
            'is_active' => true,
            'order' => 2
        ]);

        Package::create([
            'name' => 'Premium',
            'credits' => 150,
            'price_brl' => 50.00,
            'is_active' => true,
            'order' => 3
        ]);
    }
}
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            GamePackageSeeder::class,
            PackageSeeder::class,
            FirewallSeeder::class,
            PageSeeder::class,
        ]);

        $user = User::updateOrCreate(
            ['email' => 'reginaldo@reginaldo.com'],
            [
                'name' => 'Reginaldo',
                'nickname' => 'RegiMaster',
                'password' => Hash::make('scrolllock'),
                'role' => 'admin',
                'status' => 'active',
                'birth_date' => '1990-01-01',
                'country' => 'Brasil',
                'language' => 'pt_BR',
                'is_verified' => true,
            ]
        );

        $user->wallet()->firstOrCreate([]);
    }
}
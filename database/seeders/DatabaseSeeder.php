<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            GamePackageSeeder::class,
            PackageSeeder::class,
        ]);
        $user = User::firstOrCreate(
            ['email' => 'reginaldo@reginaldo.com'],
            [
                'name' => 'Reginaldo',
                'password' => Hash::make('scrolllock'),
            ]
        );

        // Garante wallet
        $user->wallet()->firstOrCreate([]);
    }
}

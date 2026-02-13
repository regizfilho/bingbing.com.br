<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WhitelistIp;

class FirewallSeeder extends Seeder
{
    public function run(): void
    {
        WhitelistIp::updateOrCreate(['ip' => '127.0.0.1'], ['label' => 'Localhost IPv4']);
        WhitelistIp::updateOrCreate(['ip' => '::1'], ['label' => 'Localhost IPv6']);
    }
}
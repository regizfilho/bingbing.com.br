<?php

use App\Jobs\ChargeAbandonedGames;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ChargeAbandonedGames())->everyFourHours();

// Cleanup de subscriptions de notificações push inativas
Schedule::command('notifications:cleanup-subscriptions')
    ->daily()
    ->at('03:00')
    ->description('Remove subscriptions inativas há mais de 30 dias');
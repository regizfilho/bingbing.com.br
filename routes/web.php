<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\PushNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('track')->group(function () {

    Route::view('/', 'leading')->name('home');

    // Rotas de notificação push REQUEREM autenticação
    Route::middleware('auth')->group(function () {
        Route::post('/api/push/subscribe', [PushNotificationController::class, 'subscribe']);
        Route::post('/api/push/unsubscribe', [PushNotificationController::class, 'unsubscribe']);
        
        // Click tracking com rate limiting
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/notifications/click/{notification:uuid}', [PushNotificationController::class, 'click'])
                ->name('notifications.click');
                
            Route::get('/notifications/click/{notification:uuid}', [PushNotificationController::class, 'clickGet'])
                ->name('notifications.click.get');
        });
    });

    Route::middleware('guest')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::livewire('/login', 'pages::auth.login')->name('login');
            Route::livewire('/register', 'pages::auth.register')->name('register');
            Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('forgot-password');
            Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('reset-password');
        });
    });

    Route::middleware('auth')->group(function () {

        Route::prefix('admin')->middleware('firewall')->name('admin.')->group(function () {
            Route::livewire('/', 'pages::admin.index')->name('index');
            Route::livewire('/pages', 'pages::admin.pages.index')->name('pages.index');
            Route::livewire('/security', 'pages::admin.security.index')->name('security.index');
            Route::livewire('/notification', 'pages::admin.notification.push')->name('notification.push');

            Route::prefix('marketing')->name('marketing.')->group(function () {
                Route::livewire('/coupon', 'pages::admin.marketing.coupon')->name('coupon');
                Route::livewire('/coupon/analytics', 'pages::admin.marketing.coupon-analytics')->name('coupon.analytics');
            });

            Route::prefix('finance')->name('finance.')->group(function () {
                Route::livewire('/', 'pages::admin.finance.index')->name('home');
                Route::livewire('/packs', 'pages::admin.finance.packs')->name('packs');
                Route::livewire('/refound', 'pages::admin.finance.refound')->name('refound');
                Route::livewire('/credit', 'pages::admin.finance.credit')->name('credit');
            });

            Route::prefix('users')->name('users.')->group(function () {
                Route::livewire('/', 'pages::admin.users.index')->name('home');
                Route::livewire('/profile/{uuid}', 'pages::admin.users.profile')->name('profile');
                Route::livewire('/on', 'pages::admin.users.live')->name('live');
                Route::livewire('/anaytics', 'pages::admin.users.anaytics')->name('anaytics');
            });
        });

        Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

        Route::prefix('player')->name('player.')->group(function () {
            Route::livewire('/profile/{uuid?}', 'pages::player.profile')->name('profile');
        });

        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::livewire('/', 'pages::wallet.index')->name('index');
            Route::livewire('/transactions', 'pages::wallet.transactions')->name('transactions');
        });

        Route::prefix('games')->name('games.')->group(function () {
            Route::livewire('/', 'pages::games.index')->name('index');
            Route::livewire('/create', 'pages::games.create')->name('create');
            Route::livewire('/{uuid}/edit', 'pages::games.edit')->name('edit');
            Route::livewire('/{uuid}', 'pages::games.play')->name('play');
        });

        Route::livewire('/join/{invite_code}', 'pages::games.join')->name('games.join');
        Route::livewire('/rankings', 'pages::rankings.index')->name('rankings.index');

        Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    });

    Route::livewire('/display/{uuid}', 'pages::games.display')->name('games.display');
    Route::livewire('/{slug}', 'pages::index')->name('pages');
});

require __DIR__ . '/auth.php';
<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Laravel 12
|--------------------------------------------------------------------------
*/

Route::middleware('track')->group(function () {

    Route::view('/', 'leading')->name('home');

    /*
    |--------------------------------------------------------------------------
    | Guest Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('guest')->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::livewire('/login', 'pages::auth.login')->name('login');
            Route::livewire('/register', 'pages::auth.register')->name('register');
            Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('forgot-password');
            Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')
                ->name('reset-password');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Admin (Firewall Protected)
        |--------------------------------------------------------------------------
        */
        Route::prefix('admin')
            ->middleware('firewall')
            ->name('admin.')
            ->group(function () {

                Route::livewire('/', 'pages::admin.index')->name('index');
                Route::livewire('/pages', 'pages::admin.pages.index')->name('pages.index');
                Route::livewire('/security', 'pages::admin.security.index')->name('security.index');

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

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Player
        |--------------------------------------------------------------------------
        */
        Route::prefix('player')->name('player.')->group(function () {
            Route::livewire('/profile/{uuid?}', 'pages::player.profile')->name('profile');
        });

        /*
        |--------------------------------------------------------------------------
        | Wallet
        |--------------------------------------------------------------------------
        */
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::livewire('/', 'pages::wallet.index')->name('index');
            Route::livewire('/transactions', 'pages::wallet.transactions')->name('transactions');
        });

        /*
        |--------------------------------------------------------------------------
        | Games (Host)
        |--------------------------------------------------------------------------
        */
        Route::prefix('games')->name('games.')->group(function () {
            Route::livewire('/', 'pages::games.index')->name('index');
            Route::livewire('/create', 'pages::games.create')->name('create');
            Route::livewire('/{uuid}/edit', 'pages::games.edit')->name('edit');
            Route::livewire('/{uuid}', 'pages::games.play')->name('play');
        });

        /*
        |--------------------------------------------------------------------------
        | Join Game
        |--------------------------------------------------------------------------
        */
        Route::livewire('/join/{invite_code}', 'pages::games.join')->name('games.join');

        /*
        |--------------------------------------------------------------------------
        | Rankings
        |--------------------------------------------------------------------------
        */
        Route::livewire('/rankings', 'pages::rankings.index')->name('rankings.index');

        /*
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        */
        Route::post('/logout', [SessionController::class, 'destroy'])
            ->name('logout');
    });

    /*
    |--------------------------------------------------------------------------
    | Public Display (TV / Telão)
    |--------------------------------------------------------------------------
    */
    Route::livewire('/display/{uuid}', 'pages::games.display')
        ->name('games.display');

}); // Fim do middleware track

require __DIR__ . '/auth.php';
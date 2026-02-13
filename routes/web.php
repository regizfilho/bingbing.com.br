<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'leading');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('forgot-request');
    Route::livewire('reset-password/{token}', 'pages::auth.reset-password')
        ->name('password.reset');
});

Route::middleware(['auth'])->group(function () {

    Route::middleware(['firewall'])->group(function () {
        Route::livewire('/', 'pages::admin.index')->name('admin');
        Route::livewire('/pages', 'pages::admin.pages.index')->name('admin.pages.index');
        Route::livewire('/security', 'pages::admin.security.index')->name('admin.security.index');
    });


    Route::livewire('/player/profile/{uuid?}', 'pages::player.profile')->name('player.profile');

    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

    // Wallet
    Route::livewire('/wallet', 'pages::wallet.index')->name('wallet.index');
    Route::livewire('/wallet/transactions', 'pages::wallet.transactions')->name('wallet.transactions');

    // Games (HOST)
    Route::livewire('/games', 'pages::games.index')->name('games.index');
    Route::livewire('/games/create', 'pages::games.create')->name('games.create');
    Route::livewire('/games/{uuid}/edit', 'pages::games.edit')->name('games.edit');
    Route::livewire('/games/{uuid}', 'pages::games.play')->name('games.play');

    // Join (requer auth)
    Route::livewire('/join/{invite_code}', 'pages::games.join')->name('games.join');

    // Rankings
    Route::livewire('/rankings', 'pages::rankings.index')->name('rankings.index');
});

// Rota PÚBLICA para tela de display (TV/telão)
Route::livewire('/display/{uuid}', 'pages::games.display')->name('games.display');

Route::post('/logout', [SessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

require __DIR__ . '/auth.php';

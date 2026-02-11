<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');

    // Wallet ...
    Route::livewire('/wallet', 'pages::wallet.index')->name('wallet.index');
    Route::livewire('/wallet/transactions', 'pages::wallet.transactions')->name('wallet.transactions');

    // Games
    Route::livewire('/games', 'pages::games.index')->name('games.index');
    Route::livewire('/games/create', 'pages::games.create')->name('games.create');
    Route::livewire('/games/{game:uuid}/edit', 'pages::games.edit')->name('games.edit');

    // ← Rota para JOGAR / gerenciar a partida (usa UUID)
    Route::livewire('/games/{game:uuid}', 'pages::games.play')->name('games.play');

    // Rankings
    Route::livewire('/rankings', 'pages::rankings.index')->name('rankings.index');
});

// Public / Join (sem auth, ou com auth opcional)
Route::livewire('/join/{invite_code}', 'pages::games.join')->name('games.join');
Route::livewire('/games/host/{game}', 'pages::games.host')->name('games.host');

require __DIR__.'/auth.php';
<?php

use App\Livewire\Display\GameDisplay;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::middleware(['auth'])->group(function () {
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

// Rota PÚBLICA para tela de display (TV/telão) - não requer autenticação
Route::livewire('/display/{uuid}', 'pages::games.display')->name('games.display');
//Route::get('/display/{uuid}', GameDisplay::class)->name('games.display');

require __DIR__.'/auth.php';
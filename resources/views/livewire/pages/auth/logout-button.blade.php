<?php

use App\Livewire\Actions\Logout;
use function Livewire\Volt\action;

// Definimos a ação de logout vinculada à sua classe Action
$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/');
};

?>

<div class="w-full">
    <button wire:click="logout" 
        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-500 hover:bg-red-500/10 transition-colors group text-left border-none outline-none cursor-pointer">
        <span class="text-sm group-hover:rotate-12 transition-transform">🚪</span>
        <span class="text-[10px] font-black uppercase tracking-widest italic">Finalizar Sessão</span>
    </button>
</div>
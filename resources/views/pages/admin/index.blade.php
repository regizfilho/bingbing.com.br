<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] 
    #[Title('Minha Página Admin')] // Opcional: Define o título da aba
class extends Component
{
    // Sua lógica aqui
};
?>

<div>
    {{-- Para o layout admin, definimos o slot do header para aparecer no topo --}}
    <x-slot name="header">
        Visão Geral do Sistema
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Exemplo de Card Moderno dentro do layout --}}
        <div class="bg-[#0f1117] border border-white/5 p-6 rounded-[2rem] shadow-2xl">
            <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Status</p>
            <h3 class="text-2xl font-black text-white italic">OPERACIONAL</h3>
        </div>

        <div class="col-span-2 bg-[#0f1117] border border-white/5 p-8 rounded-[2rem]">
            <p class="text-slate-400 font-medium">
                Bem-vindo ao painel, <span class="text-indigo-400">{{ auth()->user()->name }}</span>. 
                Este conteúdo está agora dentro da área de scroll do layout administrativo.
            </p>
        </div>
    </div>
</div>
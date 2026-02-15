<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div 
    x-data="{ show: !localStorage.getItem('cookies_accepted') }" 
    x-show="show" 
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    class="fixed bottom-6 left-6 right-6 md:left-auto md:right-6 md:max-w-md z-[200]"
>
    <div class="glass rounded-2xl p-6 shadow-2xl border border-white/10">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-blue-600/20 rounded-xl flex items-center justify-center flex-shrink-0">
                <span class="text-2xl">🍪</span>
            </div>
            
            <div class="flex-1">
                <h3 class="font-bold text-white text-lg mb-2">
                    Cookies e Privacidade
                </h3>
                
                <p class="text-sm text-slate-400 leading-relaxed mb-4">
                    Usamos cookies para melhorar sua experiência, personalizar conteúdo e analisar o tráfego. 
                    Ao continuar navegando, você concorda com nossa política de cookies.
                </p>
                
                <div class="flex flex-wrap gap-2 text-xs text-slate-500 mb-4">
                    <a href="/politica-de-privacidade" class="hover:text-blue-400 transition underline">
                        Política de Privacidade
                    </a>
                    <span>•</span>
                    <a href="/termos-de-uso" class="hover:text-blue-400 transition underline">
                        Termos de Uso
                    </a>
                    <span>•</span>
                    <a href="/cookies" class="hover:text-blue-400 transition underline">
                        Uso de Cookies
                    </a>
                </div>
                
                <button 
                    @click="localStorage.setItem('cookies_accepted', 'true'); show = false"
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-semibold transition-all shadow-lg shadow-blue-600/20 active:scale-[0.98] text-sm"
                >
                    Aceitar e continuar
                </button>
            </div>
        </div>
    </div>
</div>
<div x-data="{ 
        show: false, 
        text: '', 
        type: 'success',
        timeout: null 
    }"
    x-on:notify.window="
        show = true; 
        text = $event.detail.text; 
        type = $event.detail.type;
        clearTimeout(timeout);
        timeout = setTimeout(() => show = false, 5000)
    "
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2 scale-90"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-200"
    class="fixed top-8 right-8 z-[9999] min-w-[350px]"
    style="display: none;">
    
    <div :class="{
        'bg-emerald-600 border-emerald-400/30 shadow-emerald-950/40': type === 'success',
        'bg-red-600 border-red-400/30 shadow-red-950/40': type === 'error',
        'bg-blue-600 border-blue-400/30 shadow-blue-950/40': type === 'info'
    }" class="px-6 py-5 rounded-[2rem] border shadow-2xl flex items-center justify-between backdrop-blur-xl">
        
        <div class="flex items-center gap-4">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-sm">
                <template x-if="type === 'success'"><span>✓</span></template>
                <template x-if="type === 'error'"><span>✕</span></template>
                <template x-if="type === 'info'"><span>!</span></template>
            </div>
            <div class="flex flex-col">
                <span class="text-white font-black uppercase italic text-[11px] tracking-[0.1em]" x-text="text"></span>
                <span class="text-white/60 text-[8px] font-bold uppercase tracking-widest">Sistema de Créditos</span>
            </div>
        </div>

        <button @click="show = false" class="text-white/40 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
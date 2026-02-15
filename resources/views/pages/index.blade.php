<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;
use App\Models\Page;

new #[Layout('layouts.guest')] class extends Component {
   
    public Page $page;

    public function mount(string $slug): void
    {
        try {
            $this->page = Page::where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            Log::info('Page accessed', [
                'page_id' => $this->page->id,
                'slug' => $slug,
                'ip' => request()->ip(),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Page not found', [
                'slug' => $slug,
                'ip' => request()->ip(),
            ]);

            abort(404);
        } catch (\Exception $e) {
            Log::error('Page load failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(500);
        }
    }
};
?>

<div class="min-h-screen flex flex-col justify-center items-center bg-[#05070a] px-4 py-10 relative overflow-hidden">
    <div class="absolute top-1/4 -left-20 w-80 h-80 bg-blue-600/10 blur-[120px] rounded-full"></div>
    <div class="absolute bottom-1/4 -right-20 w-80 h-80 bg-purple-600/10 blur-[120px] rounded-full"></div>

    <div class="w-full max-w-4xl relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-blue-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <span class="text-white font-black text-2xl italic">B</span>
                </div>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic leading-none">
                {{ $page->title }}
            </h1>
            <div class="flex items-center justify-center gap-4 mt-4 text-[9px] font-black text-slate-600 uppercase tracking-widest italic">
                <span>Criado em {{ $page->created_at->format('d/m/Y') }}</span>
                <span>•</span>
                <span>Atualizado em {{ $page->updated_at->format('d/m/Y') }}</span>
            </div>
        </div>

        <div class="bg-[#0b0d11]/80 backdrop-blur-xl border border-white/10 rounded-[2.5rem] p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-purple-600 to-blue-600"></div>

            <div class="prose prose-invert prose-sm max-w-none">
                <div class="text-slate-300 leading-relaxed">
                    {!! $page->content !!}
                </div>
            </div>

            <div class="mt-8 pt-8 border-t border-white/5 text-center">
                <a href="{{ route('home') }}" wire:navigate
                    class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white px-8 py-4 rounded-[1.5rem] font-black text-xs uppercase tracking-[0.2em] italic transition-all shadow-xl shadow-purple-600/20 group">
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Voltar para Home
                </a>
            </div>
        </div>
        
        <p class="text-center mt-8 text-[8px] font-black text-slate-700 uppercase tracking-[0.4em] italic">
            &copy; 2026 BingBing Social Club // Diversão Levada a Sério
        </p>
    </div>
</div>
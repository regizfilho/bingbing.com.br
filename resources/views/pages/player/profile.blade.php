<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    use WithFileUploads;

    public $uuid;
    public bool $isEditing = false;

    // Foto de Perfil
    public $avatar;

    // Propriedades do Formulário (Todos os campos restaurados)
    public $name;
    public $nickname;
    public $email;
    public $phone_number;
    public $birth_date;
    public $gender;
    public $instagram;
    public $city;
    public $state;
    public $bio;

    // Segurança
    public $current_password;
    public $new_password;
    public $new_password_confirmation;

    public function mount($uuid = null)
    {
        if (!$uuid) {
            if (Auth::check()) {
                $this->uuid = Auth::user()->uuid;
            } else {
                return redirect()->route('auth.login');
            }
        } else {
            $this->uuid = $uuid;
        }

        if (Auth::check() && Auth::user()->uuid === $this->uuid) {
            $this->loadFormData();
        }
    }

    public function loadFormData()
    {
        $player = $this->player;
        $this->fill($player->only([
            'name', 'nickname', 'email', 'phone_number', 
            'gender', 'instagram', 'city', 'state', 'bio'
        ]));

        if ($player->birth_date) {
            $this->birth_date = \Carbon\Carbon::parse($player->birth_date)->format('Y-m-d');
        }
    }

    public function toggleEdit()
    {
        if (Auth::user()->uuid !== $this->uuid) return;
        
        $this->isEditing = !$this->isEditing;
        
        if($this->isEditing) {
            $this->loadFormData();
            $this->dispatch('notify', text: 'Modo de edição ativado!', type: 'info');
        }
    }

    #[Computed]
    public function player()
    {
        return User::where('uuid', $this->uuid)->with(['rank'])->firstOrFail();
    }

    #[Computed]
    public function stats()
    {
        $rank = $this->player->rank;
        $vitorias = $rank?->total_wins ?? 0;
        $partidas = max($rank?->total_games ?? 0, $vitorias);
        return [
            'vitorias' => $vitorias,
            'partidas' => $partidas,
            'taxa' => $partidas > 0 ? number_format(($vitorias / $partidas) * 100, 1) : '0.0',
        ];
    }

    public function updatedAvatar()
    {
        $this->validate(['avatar' => 'image|max:1024']);

        if ($this->player->avatar_path) {
            Storage::disk('public')->delete($this->player->avatar_path);
        }

        $path = $this->avatar->store('avatars', 'public');
        $this->player->update(['avatar_path' => $path]);

        $this->dispatch('notify', text: 'Foto de perfil atualizada com sucesso!', type: 'success');
    }

    public function removeAvatar()
    {
        if ($this->player->avatar_path) {
            Storage::disk('public')->delete($this->player->avatar_path);
            $this->player->update(['avatar_path' => null]);
            $this->avatar = null;
            $this->dispatch('notify', text: 'Foto de perfil removida.', type: 'info');
        }
    }

    public function updateProfile()
    {
        if (Auth::user()->uuid !== $this->uuid) return;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:50', Rule::unique('users')->ignore(Auth::id())],
            'email' => ['required', 'email', Rule::unique('users')->ignore(Auth::id())],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date'],
            'instagram' => ['nullable', 'string', 'max:100'],
        ]);

        $this->player->update([
            'name' => $this->name,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date,
            'instagram' => $this->instagram,
            'city' => $this->city,
            'state' => $this->state,
            'bio' => $this->bio,
        ]);

        $this->isEditing = false;
        $this->dispatch('notify', text: 'Perfil atualizado com sucesso!', type: 'success');
    }

    public function updatePassword()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'min:8', 'confirmed'],
        ]);

        Auth::user()->update(['password' => Hash::make($this->new_password)]);
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->dispatch('notify', text: 'Sua senha foi alterada com segurança!', type: 'success');
    }
}; ?>

<div class="min-h-screen bg-[#05070a] text-slate-200 pb-20 selection:bg-blue-500/30 relative">
    
    <x-loading target="avatar, updateProfile, updatePassword, toggleEdit, removeAvatar" message="PROCESSANDO..." />

    <div class="max-w-6xl mx-auto px-6 py-12">

        {{-- Cabeçalho --}}
        <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-5xl font-black text-white uppercase italic tracking-tighter leading-none">
                    MEU <span class="text-blue-600">PERFIL</span>
                </h1>
                <p class="text-slate-500 font-bold text-[11px] uppercase tracking-[0.3em] italic">
                    {{ $isEditing ? 'Configurações da Conta' : 'Visualização Pública' }}
                </p>
            </div>

            @if(Auth::check() && Auth::user()->uuid === $this->uuid)
                <button wire:click="toggleEdit"
                    class="px-8 py-3 {{ $isEditing ? 'bg-blue-600 text-white' : 'bg-white/5 text-slate-400 border-white/10' }} border rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all italic">
                    {{ $isEditing ? 'SAIR DA EDIÇÃO' : 'EDITAR PERFIL' }}
                </button>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            {{-- LADO ESQUERDO --}}
            <div class="lg:col-span-4 lg:sticky lg:top-8">
                <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8 text-center shadow-2xl relative overflow-hidden">
                    
                    <div class="relative w-40 h-40 mx-auto mb-8 group/avatar">
                        <div class="absolute inset-0 bg-blue-600 opacity-20 blur-md rounded-[2.5rem]"></div>
                        <div class="relative w-full h-full bg-[#05070a] rounded-[2.5rem] border-2 border-white/10 p-1.5 overflow-hidden shadow-2xl">
                            @if($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" class="w-full h-full rounded-[2rem] object-cover">
                            @elseif($this->player->avatar_path)
                                <img src="{{ Storage::url($this->player->avatar_path) }}" class="w-full h-full rounded-[2rem] object-cover">
                            @else
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($this->player->nickname ?? $this->player->name) }}&background=0D1117&color=3b82f6&size=256&bold=true" class="w-full h-full rounded-[2rem] object-cover">
                            @endif

                            @if($isEditing)
                                <label class="absolute inset-0 bg-blue-600/80 flex flex-col items-center justify-center opacity-0 group-hover/avatar:opacity-100 transition-opacity cursor-pointer">
                                    <svg class="w-6 h-6 text-white mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                    <span class="text-[8px] font-black uppercase text-white tracking-[0.2em]">Mudar Foto</span>
                                    <input type="file" wire:model="avatar" class="hidden" accept="image/*">
                                </label>
                            @endif
                        </div>

                        @if($isEditing && $this->player->avatar_path)
                            <button wire:click="removeAvatar" class="absolute -top-1 -right-1 bg-red-500 text-white p-2 rounded-xl border-4 border-[#0b0d11] hover:bg-red-600 transition-all shadow-lg">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        @endif
                    </div>

                    <h2 class="text-3xl font-black text-white uppercase italic tracking-tighter leading-tight">
                        {{ $this->player->nickname ?: $this->player->name }}
                    </h2>
                    
                    <div class="grid grid-cols-2 gap-3 mt-10">
                        <div class="bg-white/[0.02] border border-white/5 p-5 rounded-[2rem]">
                            <span class="block text-[9px] font-black text-slate-600 uppercase mb-1 tracking-widest italic">Vitórias</span>
                            <span class="text-2xl font-black text-white italic tracking-tighter">{{ $this->stats['vitorias'] }}</span>
                        </div>
                        <div class="bg-white/[0.02] border border-white/5 p-5 rounded-[2rem]">
                            <span class="block text-[9px] font-black text-slate-600 uppercase mb-1 tracking-widest italic">Taxa</span>
                            <span class="text-2xl font-black text-blue-500 italic tracking-tighter">{{ $this->stats['taxa'] }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- LADO DIREITO --}}
            <div class="lg:col-span-8 space-y-8">
                <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8 md:p-12 shadow-2xl">
                    <div class="flex items-center gap-4 mb-10">
                        <div class="w-1 h-8 bg-blue-600 rounded-full"></div>
                        <h3 class="text-sm font-black text-white uppercase tracking-[0.2em] italic">Informações Cadastrais</h3>
                    </div>

                    @if($isEditing)
                        <form wire:submit="updateProfile" class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 italic">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Apelido (Nickname)</label>
                                <input wire:model="nickname" type="text" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                                @error('nickname') <span class="text-red-500 text-[9px] font-bold uppercase">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Nome Completo</label>
                                <input wire:model="name" type="text" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">E-mail de Acesso</label>
                                <input wire:model="email" type="email" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                                @error('email') <span class="text-red-500 text-[9px] font-bold uppercase">{{ $message }}</span> @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">WhatsApp</label>
                                <input wire:model="phone_number" type="text" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Data de Nascimento</label>
                                <input wire:model="birth_date" type="date" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none [color-scheme:dark]">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Gênero</label>
                                <select wire:model="gender" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                                    <option value="">Selecione</option>
                                    <option value="male">Masculino</option>
                                    <option value="female">Feminino</option>
                                    <option value="other">Outro</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Cidade</label>
                                <input wire:model="city" type="text" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Estado (UF)</label>
                                <input wire:model="state" type="text" maxlength="2" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none uppercase" placeholder="Ex: SP">
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Instagram (Usuário)</label>
                                <input wire:model="instagram" type="text" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none" placeholder="@seuusuario">
                            </div>

                            <div class="md:col-span-2 space-y-2 pt-4">
                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-2">Biografia / Recado</label>
                                <textarea wire:model="bio" rows="4" class="w-full bg-white/[0.03] border border-white/10 rounded-3xl px-6 py-4 text-white text-sm focus:ring-1 focus:ring-blue-600 outline-none resize-none" placeholder="Fale um pouco sobre você..."></textarea>
                            </div>

                            <div class="md:col-span-2 pt-6">
                                <button type="submit" class="w-full h-16 bg-blue-600 hover:bg-blue-500 rounded-2xl text-[11px] font-black text-white uppercase tracking-[0.3em] transition-all shadow-xl shadow-blue-600/20">
                                    SALVAR ALTERAÇÕES
                                </button>
                            </div>
                        </form>
                    @else
                        {{-- Visualização Pública com todos os dados restaurados --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 italic">
                            <div class="space-y-6">
                                <span class="text-[9px] font-black text-slate-600 uppercase tracking-widest block mb-3">Biografia</span>
                                <p class="text-sm leading-relaxed text-slate-300 bg-white/[0.02] p-6 rounded-3xl border border-white/5">
                                    {{ $this->player->bio ?? 'Nenhuma descrição informada.' }}
                                </p>
                            </div>
                            <div class="space-y-4">
                                <div class="bg-white/[0.02] p-5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Cidade/Estado</span>
                                    <span class="text-xs font-black text-white">{{ $this->player->city ?: '---' }} / {{ $this->player->state ?: '---' }}</span>
                                </div>
                                <div class="bg-white/[0.02] p-5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Gênero</span>
                                    <span class="text-xs font-black text-white">
                                        {{ match($this->player->gender) { 'male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro', default => '---' } }}
                                    </span>
                                </div>
                                <div class="bg-white/[0.02] p-5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Nascimento</span>
                                    <span class="text-xs font-black text-white">
                                        {{ $this->player->birth_date ? \Carbon\Carbon::parse($this->player->birth_date)->format('d/m/Y') : '---' }}
                                    </span>
                                </div>
                                <div class="bg-white/[0.02] p-5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Instagram</span>
                                    <span class="text-xs font-black text-blue-500">{{ $this->player->instagram ? '@'.$this->player->instagram : '---' }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                @if($isEditing)
                    {{-- Segurança Restaurada --}}
                    <div class="bg-[#0b0d11] border border-white/5 rounded-[3rem] p-8 md:p-12 shadow-2xl relative overflow-hidden">
                        <div class="flex items-center gap-4 mb-10">
                            <div class="w-1 h-8 bg-white/20 rounded-full"></div>
                            <h3 class="text-sm font-black text-white uppercase tracking-[0.2em] italic">Segurança</h3>
                        </div>

                        <form wire:submit="updatePassword" class="grid grid-cols-1 md:grid-cols-3 gap-6 italic">
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-2">Senha Atual</label>
                                <input wire:model="current_password" type="password" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm outline-none focus:ring-1 focus:ring-blue-600 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-2">Nova Senha</label>
                                <input wire:model="new_password" type="password" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm outline-none focus:ring-1 focus:ring-blue-600 transition-all">
                            </div>
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-500 uppercase tracking-widest ml-2">Confirmar</label>
                                <input wire:model="new_password_confirmation" type="password" class="w-full bg-white/[0.03] border border-white/10 rounded-2xl px-6 py-4 text-white text-sm outline-none focus:ring-1 focus:ring-blue-600 transition-all">
                            </div>
                            <div class="md:col-span-3 pt-4">
                                <button type="submit" class="w-full h-14 bg-white/5 hover:bg-white/10 border border-white/10 rounded-2xl text-[10px] font-black text-white uppercase tracking-[0.3em] transition-all italic">
                                    ATUALIZAR SENHA
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Componente de Toast Centralizado (Padronizado) --}}
    <x-toast />
</div>
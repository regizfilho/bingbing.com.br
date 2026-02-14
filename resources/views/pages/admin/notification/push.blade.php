<?php

/**
 * ============================================================================
 * Sistema de Gestão de Notificações Push
 *
 * - Laravel 12 + Livewire 4
 * - Envio para usuários específicos ou broadcast
 * - Rastreamento de cliques e engajamento
 * - Histórico completo com estatísticas
 * - Validação robusta e segurança
 * ============================================================================
 */

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Notification\PushNotification;
use App\Models\Notification\PushSubscription;
use App\Models\Notification\PushNotificationClick;
use App\Services\PushNotificationService;

new #[Layout('layouts.admin')] #[Title('Gestão de Notificações Push')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $targetFilter = 'all';

    public bool $showDrawer = false;

    public string $title = '';
    public string $body = '';
    public ?string $url = null;
    public string $icon = '/imgs/ico.png';
    public string $targetType = 'all';
    public array $selectedUserIds = [];
    public ?int $selectedNotificationId = null;

    // Nova propriedade para busca de usuários
    public string $userSearch = '';

    protected function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:300'],
            'url' => ['nullable', 'url', 'max:500'],
            'icon' => ['nullable', 'string', 'max:500'],
            'targetType' => ['required', 'in:all,user'],
        ];

        // Só valida selectedUserIds se targetType for 'user'
        if ($this->targetType === 'user') {
            $rules['selectedUserIds'] = ['required', 'array', 'min:1'];
            $rules['selectedUserIds.*'] = ['exists:users,id'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório.',
            'title.max' => 'O título não pode ter mais de 100 caracteres.',
            'body.required' => 'A mensagem é obrigatória.',
            'body.max' => 'A mensagem não pode ter mais de 300 caracteres.',
            'url.url' => 'A URL deve ser válida.',
            'targetType.required' => 'Selecione o tipo de envio.',
            'selectedUserIds.required_if' => 'Selecione pelo menos um usuário.',
            'selectedUserIds.min' => 'Selecione pelo menos um usuário.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTargetFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function notifications()
    {
        return PushNotification::query()
            ->with(['creator'])
            ->when($this->search, fn($q) => $q->where(fn($sub) => $sub->where('title', 'like', "%{$this->search}%")->orWhere('body', 'like', "%{$this->search}%")))
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->targetFilter !== 'all', fn($q) => $q->where('target_type', $this->targetFilter))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function stats()
    {
        $service = app(PushNotificationService::class);
        $subscriptionStats = $service->getSubscriptionStats();
        $notificationStats = $service->getNotificationStats();

        return array_merge($subscriptionStats, $notificationStats, [
            'total_clicks' => PushNotificationClick::count(),
            'avg_click_rate' => $this->calculateClickRate(),
        ]);
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->select('id', 'name', 'email')
            ->when($this->userSearch, fn($q) => 
                $q->where(fn($sub) => 
                    $sub->where('name', 'like', "%{$this->userSearch}%")
                       ->orWhere('email', 'like', "%{$this->userSearch}%")
                )
            )
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function selectedNotification(): ?PushNotification
    {
        return $this->selectedNotificationId ? PushNotification::with(['creator'])->find($this->selectedNotificationId) : null;
    }

    #[Computed]
    public function notificationClicks()
    {
        if (!$this->selectedNotificationId) {
            return collect();
        }

        return PushNotificationClick::where('push_notification_id', $this->selectedNotificationId)
            ->with(['user', 'subscription'])
            ->latest('clicked_at')
            ->paginate(20);
    }

    private function calculateClickRate(): float
    {
        $totalSent = PushNotification::where('status', PushNotification::STATUS_COMPLETED)->sum('total_sent');

        $totalClicks = PushNotificationClick::count();

        return $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 2) : 0;
    }

    public function openDrawer(): void
    {
        $this->reset(['title', 'body', 'url', 'selectedUserIds', 'userSearch']);
        $this->icon = '/imgs/ico.png';
        $this->targetType = 'all';
        $this->resetValidation();
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
        $this->reset(['title', 'body', 'url', 'selectedUserIds', 'userSearch']);
        $this->icon = '/imgs/ico.png';
        $this->targetType = 'all';
        $this->resetValidation();
    }

    public function create(): void
    {
        try {
            $this->validate();

            // Sanitizar inputs
            $this->title = strip_tags($this->title);
            $this->body = strip_tags($this->body);
            
            // Validar URL se fornecida
            if ($this->url && !filter_var($this->url, FILTER_VALIDATE_URL)) {
                $this->addError('url', 'URL inválida');
                return;
            }

            Log::info('Iniciando criação de notificação', [
                'targetType' => $this->targetType,
                'selectedUserIds' => $this->selectedUserIds,
            ]);

            $notification = DB::transaction(function () {
                $targetFilters = $this->targetType === 'user' ? ['user_ids' => $this->selectedUserIds] : null;

                $notification = PushNotification::create([
                    'uuid' => Str::uuid(),
                    'created_by' => auth()->id(),
                    'title' => $this->title,
                    'body' => $this->body,
                    'url' => $this->url,
                    'icon' => $this->icon ?: '/imgs/ico.png',
                    'badge' => $this->icon ?: '/imgs/ico.png',
                    'target_type' => $this->targetType,
                    'target_filters' => $targetFilters,
                    'status' => PushNotification::STATUS_PENDING,
                ]);

                Log::info('Notificação criada no banco', [
                    'id' => $notification->id,
                    'target_type' => $notification->target_type,
                    'target_filters' => $notification->target_filters,
                ]);

                return $notification;
            });

            // Processar via Job se houver muitas subscriptions, senão inline
            $subscriptionsCount = PushSubscription::where('is_active', true)
                ->when($this->targetType === 'user', fn($q) => $q->whereIn('user_id', $this->selectedUserIds))
                ->count();

            if ($subscriptionsCount > 50) {
                // Usar Job para processar em background
                dispatch(function () use ($notification) {
                    $service = app(PushNotificationService::class);
                    $service->processNotification($notification);
                })->afterResponse();
                
                Log::info('Notificação enviada para processamento em background', [
                    'id' => $notification->id,
                    'subscriptions_count' => $subscriptionsCount
                ]);
            } else {
                // Processar imediatamente
                $service = app(PushNotificationService::class);
                $service->processNotification($notification);
                
                Log::info('Notificação processada imediatamente', [
                    'id' => $notification->id,
                    'subscriptions_count' => $subscriptionsCount
                ]);
            }

            $this->dispatch('notify', type: 'success', text: 'Notificação enviada com sucesso!');
            $this->closeDrawer();
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Erro de validação', ['errors' => $e->errors()]);
            throw $e;
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar notificação', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', type: 'error', text: 'Erro ao enviar: ' . $e->getMessage());
        }
    }

    public function sendTestNotification(): void
    {
        try {
            $service = app(PushNotificationService::class);
            $count = $service->notifyUser(auth()->id(), '🎉 Notificação de Teste', 'Esta é uma notificação de teste do sistema. Tudo funcionando perfeitamente!', url('/'));

            if ($count > 0) {
                $this->dispatch('notify', type: 'success', text: 'Notificação de teste enviada!');
            } else {
                $this->dispatch('notify', type: 'warning', text: 'Você não possui subscrições ativas.');
            }
        } catch (\Exception $e) {
            Log::error('Erro ao enviar teste', ['error' => $e->getMessage()]);
            $this->dispatch('notify', type: 'error', text: 'Erro ao enviar: ' . $e->getMessage());
        }
    }

    public function viewDetails(int $id): void
    {
        $this->selectedNotificationId = $id;
        $this->dispatch('open-modal', name: 'details-notification-modal');
    }

    public function resend(int $id): void
    {
        try {
            $notification = PushNotification::findOrFail($id);

            $newNotification = PushNotification::create([
                'uuid' => Str::uuid(),
                'created_by' => auth()->id(),
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->url,
                'icon' => $notification->icon,
                'badge' => $notification->badge,
                'target_type' => $notification->target_type,
                'target_filters' => $notification->target_filters,
                'status' => PushNotification::STATUS_PENDING,
            ]);

            dispatch(function () use ($newNotification) {
                $service = app(PushNotificationService::class);
                $service->processNotification($newNotification);
            })->afterResponse();

            $this->dispatch('notify', type: 'success', text: 'Notificação reenviada!');
            
        } catch (\Exception $e) {
            Log::error('Erro ao reenviar', ['error' => $e->getMessage()]);
            $this->dispatch('notify', type: 'error', text: 'Erro ao reenviar: ' . $e->getMessage());
        }
    }

    public function toggleUser(int $userId): void
    {
        if (in_array($userId, $this->selectedUserIds)) {
            $this->selectedUserIds = array_values(array_diff($this->selectedUserIds, [$userId]));
        } else {
            $this->selectedUserIds[] = $userId;
        }
    }

    public function selectAllUsers(): void
    {
        $this->selectedUserIds = $this->users->pluck('id')->toArray();
    }

    public function clearAllUsers(): void
    {
        $this->selectedUserIds = [];
    }
};
?>

<div class="p-6 min-h-screen">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">
                Notificações Push
            </h1>
            <p class="text-slate-400 text-sm mt-1">Gerencie e envie notificações para seus usuários</p>
        </div>
        <div class="flex gap-3">
            <button wire:click="sendTestNotification"
                class="px-4 py-2 bg-gray-800 border border-white/10 rounded-xl text-sm text-white hover:bg-gray-700 transition-all">
                🧪 Enviar Teste
            </button>
            <button wire:click="openDrawer"
                class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl text-sm font-semibold text-white hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg">
                ➕ Nova Notificação
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-400 text-sm">Usuários Inscritos</span>
                <span class="text-2xl">👥</span>
            </div>
            <div class="text-3xl font-bold text-white">{{ number_format($this->stats['total_users_subscribed']) }}</div>
            <div class="text-xs text-emerald-500 mt-1">{{ number_format($this->stats['active_subscriptions']) }}
                subscrições ativas</div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-400 text-sm">Total Enviadas</span>
                <span class="text-2xl">📤</span>
            </div>
            <div class="text-3xl font-bold text-white">{{ number_format($this->stats['total_sent']) }}</div>
            <div class="text-xs text-blue-500 mt-1">{{ number_format($this->stats['total_success']) }} com sucesso</div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-400 text-sm">Taxa de Cliques</span>
                <span class="text-2xl">👆</span>
            </div>
            <div class="text-3xl font-bold text-white">{{ $this->stats['avg_click_rate'] }}%</div>
            <div class="text-xs text-purple-500 mt-1">{{ number_format($this->stats['total_clicks']) }} cliques totais
            </div>
        </div>

        <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-400 text-sm">Pendentes</span>
                <span class="text-2xl">⏳</span>
            </div>
            <div class="text-3xl font-bold text-white">{{ number_format($this->stats['pending_notifications']) }}</div>
            <div class="text-xs text-orange-500 mt-1">Aguardando envio</div>
        </div>
    </div>

    <div class="bg-[#0f172a] border border-white/5 rounded-2xl p-6 mb-6">
        <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
            <div class="flex-1 relative">
                <input wire:model.live.debounce.400ms="search" placeholder="Buscar por título ou mensagem..."
                    class="w-full bg-[#111827] border border-white/10 rounded-xl px-5 py-3 text-sm text-white pl-12 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <div class="flex gap-3">
                <select wire:model.live="statusFilter"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Status</option>
                    <option value="pending">Pendente</option>
                    <option value="processing">Processando</option>
                    <option value="completed">Completo</option>
                    <option value="failed">Falhou</option>
                </select>

                <select wire:model.live="targetFilter"
                    class="bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="all">Todos os Alvos</option>
                    <option value="all">Broadcast</option>
                    <option value="user">Usuários Específicos</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-[#0f172a] border border-white/5 rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/30 text-slate-500 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-4 text-left font-semibold">Notificação</th>
                        <th class="p-4 text-left font-semibold">Alvo</th>
                        <th class="p-4 text-center font-semibold">Status</th>
                        <th class="p-4 text-right font-semibold">Enviadas</th>
                        <th class="p-4 text-right font-semibold">Cliques</th>
                        <th class="p-4 text-right font-semibold">Data</th>
                        <th class="p-4 text-right font-semibold">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse ($this->notifications as $notification)
                        <tr class="hover:bg-white/5 transition-all group">
                            <td class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="text-2xl">
                                        @if ($notification->icon)
                                            <img src="{{ $notification->icon }}" class="w-10 h-10 rounded-lg"
                                                alt="">
                                        @else
                                            🔔
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-white font-semibold truncate">{{ $notification->title }}</div>
                                        <div class="text-slate-400 text-xs truncate">{{ $notification->body }}</div>
                                        @if ($notification->url)
                                            <div class="text-blue-400 text-xs truncate mt-1">🔗
                                                {{ $notification->url }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                @if ($notification->target_type === 'all')
                                    <span
                                        class="px-3 py-1 bg-purple-500/10 text-purple-400 rounded-full text-xs font-medium border border-purple-500/20">
                                        📡 Broadcast
                                    </span>
                                @else
                                    <span
                                        class="px-3 py-1 bg-blue-500/10 text-blue-400 rounded-full text-xs font-medium border border-blue-500/20">
                                        👤 {{ count($notification->target_filters['user_ids'] ?? []) }} usuário(s)
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-center">
                                @if ($notification->status === 'completed')
                                    <span
                                        class="px-3 py-1 bg-emerald-500/10 text-emerald-400 rounded-full text-xs font-bold border border-emerald-500/20">
                                        ✓ Enviada
                                    </span>
                                @elseif($notification->status === 'processing')
                                    <span
                                        class="px-3 py-1 bg-blue-500/10 text-blue-400 rounded-full text-xs font-bold border border-blue-500/20">
                                        ⏳ Enviando
                                    </span>
                                @elseif($notification->status === 'pending')
                                    <span
                                        class="px-3 py-1 bg-orange-500/10 text-orange-400 rounded-full text-xs font-bold border border-orange-500/20">
                                        ⏸ Pendente
                                    </span>
                                @else
                                    <span
                                        class="px-3 py-1 bg-red-500/10 text-red-400 rounded-full text-xs font-bold border border-red-500/20">
                                        ✗ Falhou
                                    </span>
                                @endif
                            </td>
                            <td class="p-4 text-right">
                                <div class="text-white font-bold">{{ number_format($notification->total_sent) }}</div>
                                <div class="text-emerald-500 text-xs">{{ number_format($notification->total_success) }}
                                    sucesso</div>
                            </td>
                            <td class="p-4 text-right">
                                @php
                                    $clicks = \App\Models\Notification\PushNotificationClick::where(
                                        'push_notification_id',
                                        $notification->id,
                                    )->count();
                                    $clickRate =
                                        $notification->total_sent > 0
                                            ? round(($clicks / $notification->total_sent) * 100, 1)
                                            : 0;
                                @endphp
                                <div class="text-white font-bold">{{ $clicks }}</div>
                                <div class="text-purple-500 text-xs">{{ $clickRate }}% taxa</div>
                            </td>
                            <td class="p-4 text-right text-slate-400 text-xs">
                                {{ $notification->sent_at?->format('d/m H:i') ?? $notification->created_at->format('d/m H:i') }}
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="viewDetails({{ $notification->id }})"
                                        class="text-indigo-400 hover:text-indigo-300 text-xs font-medium transition-colors px-3 py-1 rounded-lg hover:bg-indigo-500/10">
                                        Ver Detalhes
                                    </button>
                                    @if ($notification->status === 'completed')
                                        <button wire:click="resend({{ $notification->id }})"
                                            class="text-emerald-400 hover:text-emerald-300 text-xs font-medium transition-colors px-3 py-1 rounded-lg hover:bg-emerald-500/10">
                                            Reenviar
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto mb-2 text-slate-500" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                Nenhuma notificação encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6 border-t border-white/5 bg-black/10">
            {{ $this->notifications->links() }}
        </div>
    </div>

    @if ($showDrawer)
        <x-drawer :show="$showDrawer" max-width="md" wire:model="showDrawer">
            <div class="border-b border-white/10 flex items-center justify-between mb-6 pb-4">
                <div>
                    <h2 class="text-xl font-bold text-white">Nova Notificação Push</h2>
                    <p class="text-slate-400 text-sm">Envie notificações para seus usuários</p>
                </div>
                <button wire:click="closeDrawer"
                    class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-white/5 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="create" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Título *</label>
                    <input wire:model="title" type="text" maxlength="100"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Ex: Nova funcionalidade disponível!">
                    @error('title')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Mensagem *</label>
                    <textarea wire:model="body" rows="3" maxlength="300"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Escreva a mensagem da notificação..."></textarea>
                    @error('body')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">URL de Destino (opcional)</label>
                    <input wire:model="url" type="url"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="https://exemplo.com/pagina">
                    @error('url')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Enviar Para *</label>
                    <select wire:model.live="targetType"
                        class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="all">📡 Todos os Usuários (Broadcast)</option>
                        <option value="user">👤 Usuários Específicos</option>
                    </select>
                    @error('targetType')
                        <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @if ($targetType === 'user')
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Selecionar Usuários * 
                            <span class="text-indigo-400 text-xs">({{ count($selectedUserIds) }} selecionado(s))</span>
                        </label>
                        
                        <div class="mb-3 relative">
                            <input wire:model.live.debounce.300ms="userSearch" type="text"
                                class="w-full bg-[#111827] border border-white/10 rounded-xl px-4 py-2 text-sm text-white pl-10 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Buscar por nome ou email...">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <div class="flex gap-2 mb-2">
                            <button type="button" wire:click="selectAllUsers"
                                class="px-3 py-1 bg-indigo-600/20 text-indigo-400 text-xs rounded-lg hover:bg-indigo-600/30 transition">
                                Selecionar Todos
                            </button>
                            <button type="button" wire:click="clearAllUsers"
                                class="px-3 py-1 bg-red-600/20 text-red-400 text-xs rounded-lg hover:bg-red-600/30 transition">
                                Limpar Seleção
                            </button>
                        </div>

                        <div class="bg-[#111827] border border-white/10 rounded-xl max-h-64 overflow-y-auto custom-scrollbar">
                            @forelse ($this->users as $user)
                                <label 
                                    class="flex items-center gap-3 p-3 hover:bg-white/5 cursor-pointer transition border-b border-white/5 last:border-0">
                                    <input type="checkbox" 
                                        wire:click="toggleUser({{ $user->id }})"
                                        {{ in_array($user->id, $selectedUserIds) ? 'checked' : '' }}
                                        class="w-4 h-4 bg-[#111827] border-white/20 rounded text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-white text-sm font-medium truncate">{{ $user->name }}</div>
                                        <div class="text-slate-400 text-xs truncate">{{ $user->email }}</div>
                                    </div>
                                </label>
                            @empty
                                <div class="p-6 text-center text-slate-400 text-sm">
                                    @if($userSearch)
                                        Nenhum usuário encontrado para "{{ $userSearch }}"
                                    @else
                                        Nenhum usuário disponível
                                    @endif
                                </div>
                            @endforelse
                        </div>
                        
                        @error('selectedUserIds')
                            <p class="text-red-400 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-800 disabled:opacity-50 transition py-3 rounded-xl text-sm font-medium text-white flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="create">📤 Enviar Notificação</span>
                    <span wire:loading wire:target="create">Enviando...</span>
                    <div wire:loading wire:target="create"
                        class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin">
                    </div>
                </button>
            </form>
        </x-drawer>
    @endif

    <x-modal name="details-notification-modal" title="Detalhes da Notificação" maxWidth="4xl">
        @if ($this->selectedNotification)
            <div class="space-y-6">
                <div class="bg-black/20 border border-white/10 rounded-xl p-6">
                    <div class="flex items-start gap-4 mb-4">
                        <div class="text-4xl">
                            @if ($this->selectedNotification->icon)
                                <img src="{{ $this->selectedNotification->icon }}" class="w-16 h-16 rounded-xl"
                                    alt="">
                            @else
                                🔔
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-white mb-1">{{ $this->selectedNotification->title }}
                            </h3>
                            <p class="text-slate-300 mb-3">{{ $this->selectedNotification->body }}</p>
                            @if ($this->selectedNotification->url)
                                <a href="{{ $this->selectedNotification->url }}" target="_blank"
                                    class="text-blue-400 hover:text-blue-300 text-sm">
                                    🔗 {{ $this->selectedNotification->url }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-white/10">
                        <div>
                            <div class="text-slate-500 text-xs mb-1">Status</div>
                            <div class="text-white font-semibold">{{ ucfirst($this->selectedNotification->status) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs mb-1">Enviadas</div>
                            <div class="text-white font-semibold">
                                {{ number_format($this->selectedNotification->total_sent) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs mb-1">Sucesso</div>
                            <div class="text-emerald-400 font-semibold">
                                {{ number_format($this->selectedNotification->total_success) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 text-xs mb-1">Cliques</div>
                            <div class="text-purple-400 font-semibold">
                                {{ number_format($this->notificationClicks->total()) }}</div>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-semibold text-white mb-4">Histórico de Cliques</h4>
                    @if ($this->notificationClicks->count() > 0)
                        <div class="space-y-3 max-h-96 overflow-y-auto custom-scrollbar">
                            @foreach ($this->notificationClicks as $click)
                                <div
                                    class="bg-black/20 border border-white/10 rounded-xl p-4 hover:bg-white/5 transition">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-indigo-500/20 rounded-full flex items-center justify-center text-indigo-400 font-semibold">
                                                {{ Str::upper(substr($click->user->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <div class="text-white font-medium">{{ $click->user->name }}</div>
                                                <div class="text-slate-400 text-xs">{{ $click->user->email }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-slate-300 text-sm">
                                                {{ $click->clicked_at->format('d/m/Y H:i:s') }}</div>
                                            <div class="text-slate-500 text-xs">
                                                {{ $click->clicked_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            {{ $this->notificationClicks->links() }}
                        </div>
                    @else
                        <div class="text-center py-12 text-slate-400 bg-black/20 rounded-xl">
                            <svg class="w-16 h-16 mx-auto mb-4 text-slate-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                            </svg>
                            Nenhum clique registrado ainda.
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </x-modal>

    <x-loading target="create,resend,sendTestNotification" message="Processando..." overlay />
    <x-toast position="top-right" />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #111827;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
    </style>
</div>
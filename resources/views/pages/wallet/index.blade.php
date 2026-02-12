<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use App\Models\Wallet\Package;

new class extends Component {
    public ?int $selectedPackageId = null;
    public bool $showConfirmation = false;

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    #[Computed]
    public function packages()
    {
        return Package::active()->get();
    }

    #[Computed]
    public function selectedPackage(): ?Package
    {
        return $this->selectedPackageId 
            ? Package::find($this->selectedPackageId) 
            : null;
    }

    public function selectPackage(int $packageId): void
    {
        $this->selectedPackageId = $packageId;
        $this->showConfirmation = true;
    }

    public function cancelPurchase(): void
    {
        $this->selectedPackageId = null;
        $this->showConfirmation = false;
    }

    public function confirmPurchase(): void
    {
        if (!$this->selectedPackage) {
            session()->flash('error', 'Selecione um pacote');
            return;
        }

        $this->user->wallet->credit(
            $this->selectedPackage->credits,
            "Compra do pacote {$this->selectedPackage->name}",
            $this->selectedPackage
        );

        session()->flash('success', "Pacote {$this->selectedPackage->name} comprado com sucesso! +{$this->selectedPackage->credits} créditos");
        
        $this->cancelPurchase();
        unset($this->user);
    }
};
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Carteira</h1>
        <p class="text-gray-600">Gerencie seus créditos</p>
    </div>

    @if (session('success'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-8 mb-8 text-white">
        <div class="text-sm opacity-90 mb-2">Saldo Atual</div>
        <div class="text-4xl font-bold mb-4">
            {{ number_format($this->user->wallet->balance ?? 0, 0) }} créditos
        </div>
        <div class="text-sm opacity-75">Use créditos para criar partidas premium</div>
    </div>

    @if ($showConfirmation && $this->selectedPackage)
        <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" wire:click="cancelPurchase">
            <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4" wire:click.stop>
                <h3 class="text-2xl font-bold mb-4">Confirmar Compra</h3>
                
                <div class="mb-6">
                    <div class="text-center py-6 bg-gray-50 rounded-lg mb-4">
                        <div class="text-sm text-gray-600 mb-2">Pacote</div>
                        <div class="text-2xl font-bold text-blue-600">{{ $this->selectedPackage->name }}</div>
                        <div class="text-3xl font-bold text-gray-900 mt-3">
                            {{ number_format($this->selectedPackage->credits, 0) }} créditos
                        </div>
                        <div class="text-lg text-gray-600 mt-2">
                            R$ {{ number_format($this->selectedPackage->price_brl, 2, ',', '.') }}
                        </div>
                    </div>

                    <div class="text-sm text-gray-600 text-center">
                        <p class="mb-2">⚠️ Sistema de pagamento simulado</p>
                        <p>Os créditos serão adicionados instantaneamente</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button wire:click="confirmPurchase"
                        class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                        Confirmar Compra
                    </button>
                    <button wire:click="cancelPurchase"
                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg font-semibold transition">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold">Pacotes de Créditos</h2>
            <p class="text-sm text-gray-600 mt-1">Selecione um pacote para comprar créditos</p>
        </div>
        <div class="p-6">
            @if ($this->packages->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach ($this->packages as $package)
                        <div class="border-2 rounded-lg p-6 hover:border-blue-500 transition hover:shadow-md {{ $selectedPackageId === $package->id ? 'border-blue-500 bg-blue-50' : '' }}">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-900 mb-2">{{ $package->name }}</div>
                                <div class="text-3xl font-bold text-blue-600 mb-1">
                                    {{ number_format($package->credits, 0) }}
                                </div>
                                <div class="text-sm text-gray-500 mb-4">créditos</div>
                                <div class="text-2xl font-bold text-gray-900 mb-6">
                                    R$ {{ number_format($package->price_brl, 2, ',', '.') }}
                                </div>
                                <button wire:click="selectPackage({{ $package->id }})"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition">
                                    Comprar Agora
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 text-gray-500">
                    <div class="text-lg font-semibold mb-2">Nenhum pacote disponível no momento</div>
                    <p class="text-sm">Pacotes serão liberados em breve!</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('wallet.transactions') }}" 
            class="bg-white border-2 hover:border-blue-500 rounded-lg p-6 text-center transition hover:shadow-md">
            <div class="text-xl font-semibold text-gray-900 mb-1">Ver Histórico</div>
            <div class="text-sm text-gray-600">Todas as transações realizadas</div>
        </a>
        <a href="{{ route('dashboard') }}" 
            class="bg-white border-2 hover:border-blue-500 rounded-lg p-6 text-center transition hover:shadow-md">
            <div class="text-xl font-semibold text-gray-900 mb-1">Voltar ao Dashboard</div>
            <div class="text-sm text-gray-600">Página inicial</div>
        </a>
    </div>
</div>
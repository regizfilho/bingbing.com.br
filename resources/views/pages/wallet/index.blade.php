<?php

use Livewire\Component;
use App\Models\Wallet\Package;

new class extends Component {
    public $user;
    public $packages;

    public function mount()
    {
        $this->user = auth()->user();
        $this->packages = Package::active()->get();
    }
};

?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Carteira</h1>
        <p class="text-gray-600">Gerencie seus créditos</p>
    </div>

    <!-- Saldo Atual -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-8 mb-8 text-white">
        <div class="text-sm opacity-90 mb-2">Saldo Atual</div>
        <div class="text-4xl font-bold mb-4">
            {{ number_format($user->wallet->balance ?? 0, 2, ',', '.') }} créditos
        </div>
        <div class="text-sm opacity-75">1 crédito = R$ 1,00</div>
    </div>

    <!-- Pacotes de Créditos -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold">Pacotes de Créditos</h2>
            <p class="text-sm text-gray-600 mt-1">Selecione um pacote para adicionar créditos (Pagamento não disponível)</p>
        </div>
        <div class="p-6">
            @if($packages->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($packages as $package)
                        <div class="border rounded-lg p-6 hover:border-blue-500 transition hover:shadow-md">
                            <div class="text-center">
                                <div class="text-lg font-semibold text-gray-900 mb-2">{{ $package->name }}</div>
                                <div class="text-3xl font-bold text-blue-600 mb-1">{{ number_format($package->credits, 0, ',', '.') }}</div>
                                <div class="text-sm text-gray-500 mb-4">créditos</div>
                                <div class="text-2xl font-bold text-gray-900 mb-6">R$ {{ number_format($package->price_brl, 2, ',', '.') }}</div>
                                <button 
                                    class="w-full bg-gray-300 text-gray-600 px-4 py-3 rounded-lg cursor-not-allowed font-medium"
                                    disabled
                                >
                                    Em breve
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

    <!-- Ações Rápidas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('wallet.transactions') }}" class="bg-white border-2 border-gray-200 hover:border-blue-500 rounded-lg p-6 text-center transition hover:shadow-md">
            <div class="text-xl font-semibold text-gray-900 mb-1">Ver Histórico</div>
            <div class="text-sm text-gray-600">Todas as transações realizadas</div>
        </a>
        <a href="{{ route('dashboard') }}" class="bg-white border-2 border-gray-200 hover:border-blue-500 rounded-lg p-6 text-center transition hover:shadow-md">
            <div class="text-xl font-semibold text-gray-900 mb-1">Voltar ao Dashboard</div>
            <div class="text-sm text-gray-600">Página inicial</div>
        </a>
    </div>
</div>
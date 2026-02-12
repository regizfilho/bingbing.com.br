# Games Edit

```php
<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use App\Models\Game\Game;

new class extends Component {
    public Game $game;

    #[Validate('required|min:3|max:255')]
    public string $name = '';

    #[Validate('required|in:manual,automatic')]
    public string $draw_mode = 'manual';

    #[Validate('required_if:draw_mode,automatic|integer|min:2|max:10')]
    public int $auto_draw_seconds = 3;

    #[Validate('*.name', 'required|min:2|max:255')]
    public array $prizes = [];

    #[Computed]
    public function user()
    {
        return auth()->user();
    }

    public function mount($game): void
    {
        $this->game = Game::where('uuid', $game)
            ->with(['prizes', 'package'])
            ->firstOrFail();

        if ($this->game->creator_id !== $this->user->id) {
            abort(403, 'Você não é o criador desta partida.');
        }

        if ($this->game->status !== 'draft') {
            session()->flash('error', 'Apenas partidas em rascunho podem ser editadas.');
            $this->redirect(route('games.index'), navigate: true);
            return;
        }

        $this->name = $this->game->name;
        $this->draw_mode = $this->game->draw_mode;
        $this->auto_draw_seconds = $this->game->auto_draw_seconds ?? 3;

        $this->prizes = $this->game->prizes
            ->sortBy('position')
            ->map(fn($prize) => [
                'id' => $prize->id,
                'name' => $prize->name ?? '',
                'description' => $prize->description ?? '',
            ])
            ->values()
            ->toArray();
    }

    public function addPrize(): void
    {
        $this->prizes[] = ['id' => null, 'name' => '', 'description' => ''];
    }

    public function removePrize(int $index): void
    {
        unset($this->prizes[$index]);
        $this->prizes = array_values($this->prizes);
    }

    public function update(): void
    {
        $this->validate();

        $this->game->update([
            'name' => $this->name,
            'draw_mode' => $this->draw_mode,
            'auto_draw_seconds' => $this->auto_draw_seconds,
        ]);

        $this->game->prizes()->delete();

        foreach ($this->prizes as $index => $prize) {
            $this->game->prizes()->create([
                'name' => $prize['name'],
                'description' => $prize['description'] ?? '',
                'position' => $index + 1,
            ]);
        }

        $this->game->refresh();

        session()->flash('success', 'Alterações salvas com sucesso!');
    }

    public function publish(): void
    {
        $this->validate();

        if (empty($this->prizes)) {
            session()->flash('error', 'Adicione pelo menos um prêmio antes de publicar.');
            return;
        }

        $this->game->update([
            'name' => $this->name,
            'draw_mode' => $this->draw_mode,
            'auto_draw_seconds' => $this->auto_draw_seconds,
            'status' => 'waiting',
        ]);

        $this->game->prizes()->delete();

        foreach ($this->prizes as $index => $prize) {
            $this->game->prizes()->create([
                'name' => $prize['name'],
                'description' => $prize['description'] ?? '',
                'position' => $index + 1,
            ]);
        }

        session()->flash('success', 'Partida publicada com sucesso! Compartilhe o código com os jogadores.');
        $this->redirect(route('games.play', $this->game), navigate: true);
    }
};
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-8 flex justify-between items-center flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Editar Partida</h1>
            <p class="text-gray-600">{{ $game->name }}</p>
        </div>
        <a href="{{ route('games.index') }}" class="text-gray-600 hover:text-gray-900">
            ← Voltar
        </a>
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

    <form wire:submit="update" class="space-y-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <div class="text-sm text-blue-800 font-medium mb-2">Pacote Selecionado</div>
            <div class="text-lg font-semibold text-blue-900">{{ $game->package->name ?? '—' }}</div>
            <div class="text-sm text-blue-700 mt-2">
                Máx. {{ $game->package->max_players ?? '?' }} jogadores • 
                {{ $game->package->max_cards_per_player ?? '?' }} cartela(s) por jogador
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Partida</label>
            <input type="text" wire:model.blur="name"
                class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <label class="block text-sm font-medium text-gray-700 mb-4">Modo de Sorteio</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 
                    {{ $draw_mode === 'manual' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="draw_mode" value="manual" class="sr-only">
                    <div class="font-semibold mb-1">Manual</div>
                    <div class="text-sm text-gray-600">Você controla cada sorteio</div>
                </label>

                <label class="border rounded-lg p-4 cursor-pointer transition hover:border-blue-500 
                    {{ $draw_mode === 'automatic' ? 'border-blue-500 bg-blue-50' : '' }}">
                    <input type="radio" wire:model.live="draw_mode" value="automatic" class="sr-only">
                    <div class="font-semibold mb-1">Automático</div>
                    <div class="text-sm text-gray-600">Sorteios automáticos a cada intervalo</div>
                </label>
            </div>

            @if ($draw_mode === 'automatic')
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Intervalo (segundos)</label>
                    <input type="number" wire:model.blur="auto_draw_seconds" min="2" max="10"
                        class="w-full px-4 py-2 border rounded-lg">
                    @error('auto_draw_seconds') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            @endif
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <label class="block text-sm font-medium text-gray-700">Prêmios</label>
                <button type="button" wire:click="addPrize"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm transition">
                    + Adicionar Prêmio
                </button>
            </div>

            <div class="space-y-4">
                @foreach ($prizes as $index => $prize)
                    <div class="border rounded-lg p-4">
                        <div class="flex gap-4 items-start">
                            <div class="flex-1">
                                <input type="text" wire:model.blur="prizes.{{ $index }}.name"
                                    placeholder="Nome do prêmio"
                                    class="w-full px-4 py-2 border rounded-lg mb-2">
                                @error("prizes.{$index}.name")
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror

                                <textarea wire:model.blur="prizes.{{ $index }}.description"
                                    placeholder="Descrição (opcional)" rows="2"
                                    class="w-full px-4 py-2 border rounded-lg"></textarea>
                            </div>

                            @if (count($prizes) > 1)
                                <button type="button" wire:click="removePrize({{ $index }})"
                                    class="text-red-600 hover:text-red-800 self-start mt-1">
                                    Remover
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4">
            <button type="submit"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                Salvar Alterações
            </button>

            <button type="button" wire:click="publish"
                class="flex-1 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition font-semibold">
                Publicar Partida
            </button>

            <a href="{{ route('games.index') }}"
                class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-lg transition font-semibold text-center">
                Cancelar
            </a>
        </div>
    </form>
</div>
```

---

# README.md

```markdown
# 🎰 Sistema de Bingo Online

Sistema completo de bingo online multiplayer com suporte a múltiplas rodadas, gestão de créditos e interface otimizada para TV/projetor.

## 📋 Índice

- [Visão Geral](#visão-geral)
- [Arquitetura](#arquitetura)
- [Funcionalidades](#funcionalidades)
- [Fluxo de Uso](#fluxo-de-uso)
- [Instalação](#instalação)
- [Estrutura de Telas](#estrutura-de-telas)
- [Sistema de Pacotes](#sistema-de-pacotes)
- [Sistema de Créditos](#sistema-de-créditos)
- [Tecnologias](#tecnologias)

---

## 🎯 Visão Geral

Sistema de bingo desenvolvido em **Laravel 11** e **Livewire 4** que permite criar partidas personalizadas com múltiplas rodadas, diferentes tamanhos de cartela e modos de sorteio. O sistema separa três experiências distintas: controle do organizador (host), visualização pública (TV/projetor) e interface de jogo para participantes (mobile/desktop).

### Principais Diferenciais

- ✅ **3 interfaces separadas:** Host, Display público e Jogadores
- ✅ **Múltiplas rodadas:** Gere novas cartelas automaticamente entre rodadas
- ✅ **Otimizado para TV:** Tela pública fullscreen com números gigantes
- ✅ **Mobile-first:** Interface de jogador responsiva e intuitiva
- ✅ **Sistema de créditos:** Monetização integrada com carteira virtual
- ✅ **Tempo real:** Atualizações automáticas via polling (preparado para websockets)
- ✅ **Detecção automática:** Sistema identifica BINGO automaticamente
- ✅ **Flexível:** 3 tamanhos de cartela (9, 15, 24 números)

---

## 🏗️ Arquitetura

### Arquitetura de 3 Telas

```
┌─────────────────────────────────────────────────────────────┐
│                    SISTEMA DE BINGO                         │
└─────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┼─────────────┐
                │             │             │
         ┌──────▼─────┐ ┌────▼────┐ ┌─────▼──────┐
         │    HOST    │ │ DISPLAY │ │  JOGADOR   │
         │ (Controle) │ │   (TV)  │ │  (Mobile)  │
         └────────────┘ └─────────┘ └────────────┘
```

### Modelos Principais

```
User (Usuários)
├── Wallet (Carteira de créditos)
│   └── Transactions (Histórico de movimentações)
├── Games (Criador de partidas)
└── Players (Participante de partidas)
    └── Cards (Cartelas do jogador)

Game (Partida)
├── GamePackage (Pacote contratado)
├── Players (Jogadores na partida)
├── Cards (Todas as cartelas)
├── Draws (Números sorteados)
├── Prizes (Prêmios configurados)
└── Winners (Vencedores por prêmio/rodada)
```

---

## 🚀 Funcionalidades

### Para o Organizador (Host)

- Criar partidas com pacotes Free, Básico ou Premium
- Configurar número de rodadas (1 a ilimitadas)
- Escolher tamanho das cartelas (9, 15 ou 24 números)
- Definir modo de sorteio (manual ou automático)
- Controlar visibilidade para jogadores
- Sortear números manualmente ou automaticamente
- Validar e conceder prêmios
- Iniciar múltiplas rodadas
- Finalizar partida
- Compartilhar tela pública e código de convite

### Para Jogadores

- Entrar via código de 6 dígitos
- Receber cartelas automaticamente
- Marcar números clicando
- Ver últimos números sorteados (se habilitado)
- Indicador visual de números correspondentes (se habilitado)
- Notificação ao completar BINGO
- Acompanhar prêmios e vencedores

### Para o Público (TV/Projetor)

- Visualização fullscreen sem controles
- Número atual em destaque (12rem)
- Últimos 8 números sorteados
- Grade completa de 75 números
- Lista de prêmios e vencedores
- Contador de jogadores e rodadas
- Tela de aguardo antes do início
- Tela de finalização com campeões

---

## 📱 Fluxo de Uso

### 1. Preparação (Host)

```
1. Host acessa /games/create
2. Seleciona pacote (Free/Básico/Premium)
3. Configura:
   - Nome da partida
   - Número de rodadas
   - Tamanho da cartela (9/15/24)
   - Modo de sorteio (manual/automático)
   - Visibilidade para jogadores
   - Prêmios
4. Clica em "Criar Partida"
5. Sistema debita créditos (se não for Free)
6. Partida criada em status "draft"
```

### 2. Publicação (Host)

```
1. Host acessa /games/{uuid}/edit
2. Revisa configurações
3. Clica em "Publicar Partida"
4. Status muda para "waiting"
5. Código de convite gerado (ex: ABC123)
```

### 3. Início da Partida

```
HOST:
1. Acessa /games/{uuid} (painel de controle)
2. Clica em "Abrir Tela Pública"
3. Nova aba abre: /display/{uuid}
4. Conecta TV/projetor nesta aba
5. Compartilha código ABC123 com jogadores
6. Aguarda jogadores entrarem
7. Clica em "Iniciar Partida"

JOGADORES:
1. Acessam /join/ABC123
2. Sistema gera cartelas automaticamente
3. Aguardam início

TELA PÚBLICA (TV):
1. Mostra "Aguardando Início..."
2. Exibe código de convite
3. Contador de jogadores
```

### 4. Durante a Partida

```
HOST (Painel de Controle):
- Clica em "Sortear Próximo Número" (modo manual)
  OU
- Sistema sorteia automaticamente (modo automático)
- Vê lista de BINGO detectados
- Valida e concede prêmios clicando

TELA PÚBLICA (TV):
- Mostra número sorteado (animação bounce)
- Atualiza grade de 75 números
- Exibe vencedores conforme ganham
- Auto-refresh a cada 3 segundos

JOGADORES (Mobile):
- Clicam nos números para marcar
- Veem círculo amarelo se habilitado
- Recebem alerta ao completar BINGO
- Gritam "BINGO!" para o host
```

### 5. Múltiplas Rodadas

```
1. Todos os prêmios da rodada foram concedidos
2. HOST clica em "Próxima Rodada"
3. Sistema automaticamente:
   - Reseta status dos prêmios
   - Gera NOVAS cartelas para todos
   - Limpa números sorteados
   - Incrementa contador de rodada
4. Jogadores recebem novas cartelas
5. Ciclo se repete
```

### 6. Finalização

```
HOST:
1. Clica em "Finalizar Partida"
2. Sistema valida:
   - Sem jogadores? → Reembolsa créditos
   - Sem vencedores? → Reembolsa créditos
   - Caso contrário → Consome créditos normalmente

TELA PÚBLICA:
- Mostra "Partida Finalizada!"
- Lista de campeões finais
- Organizador em destaque

JOGADORES:
- Veem tela de finalização
- Estatísticas atualizadas
```

---

## 💻 Instalação

### Requisitos

- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Node.js 18+ (para build de assets)

### Passo a Passo

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/bingo-system.git
cd bingo-system

# 2. Instale dependências PHP
composer install

# 3. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 4. Configure o banco de dados no .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bingo
DB_USERNAME=root
DB_PASSWORD=

# 5. Execute as migrations
php artisan migrate

# 6. Execute os seeders
php artisan db:seed

# 7. Instale dependências Node
npm install

# 8. Compile assets
npm run build

# 9. Inicie o servidor
php artisan serve
```

### Seeders Incluídos

```bash
# Cria pacotes de carteira
php artisan db:seed --class=WalletPackageSeeder

# Cria pacotes de jogo (Free, Básico, Premium)
php artisan db:seed --class=GamePackageSeeder

# Cria usuários de teste
php artisan db:seed --class=UserSeeder
```

---

## 📺 Estrutura de Telas

### 1️⃣ Tela do HOST (Organizador)

**Rota:** `/games/{uuid}`  
**Componente:** `games-play.php`  
**Autenticação:** Obrigatória (apenas criador)

#### Layout

```
┌────────────────────────────────────────────────┐
│  SIDEBAR (Esquerda)                            │
│  - Nome da partida + status                    │
│  - Código de convite                           │
│  - Saldo de créditos                           │
│  - Botões de ação:                             │
│    • Compartilhar Tela Pública                 │
│    • Compartilhar Convite                      │
│    • Abrir Tela Pública                        │
│    • Iniciar Partida                           │
│    • Próxima Rodada                            │
│    • Finalizar Partida                         │
├────────────────────────────────────────────────┤
│  MAIN (Direita)                                │
│  1. Painel de Controle                         │
│     - Botão "Sortear Próximo Número"           │
│     - Último número sorteado (destaque)        │
│     - Grade de 75 números                      │
│                                                │
│  2. BINGO Detectado (se houver)                │
│     - Lista de cartelas vencedoras             │
│                                                │
│  3. Gerenciar Prêmios                          │
│     - Grid de prêmios                          │
│     - Status (Disponível/Concedido)            │
│     - Vencedor da rodada                       │
│     - Botões "Conceder a [Jogador]"            │
│                                                │
│  4. Jogadores                                  │
│     - Lista com avatares                       │
│     - Número de cartelas                       │
│     - Badge "Vencedor" se aplicável            │
└────────────────────────────────────────────────┘
```

#### Recursos Visuais

- Saldo de créditos em destaque
- Alerta de reembolso se partida vazia
- Feedback visual ao sortear
- Alertas de sucesso/erro no topo

---

### 2️⃣ Tela PÚBLICA (TV/Telão)

**Rota:** `/display/{uuid}`  
**Componente:** `games-display.php`  
**Autenticação:** NÃO requerida (pública)

#### Layout (Status: Active)

```
┌────────────────────────────────────────────────────────────┐
│                    [NOME DA PARTIDA]                       │
│         Rodada: 1/3  •  Jogadores: 15                      │
├──────────────┬─────────────────────────┬───────────────────┤
│              │                         │                   │
│  ÚLTIMOS     │    NÚMERO ATUAL         │    PRÊMIOS        │
│  SORTEADOS   │                         │                   │
│              │       ┌─────────┐       │  1º - TV 50"      │
│    [42]      │       │         │       │  Winner: João     │
│    [17]      │       │   23    │       │                   │
│    [68]      │       │         │       │  2º - R$ 100      │
│    [05]      │       └─────────┘       │  Disponível       │
│    [31]      │                         │                   │
│    [56]      │    23 / 75 sorteados    │  VENCEDORES       │
│    [12]      │                         │  🏆 João          │
│    [49]      │                         │                   │
│              │                         │                   │
├──────────────┴─────────────────────────┴───────────────────┤
│         GRADE COMPLETA (75 NÚMEROS)                        │
│  [01][02][03][04][05] ... [75]                             │
│  Verde = Sorteado / Cinza = Pendente                       │
├────────────────────────────────────────────────────────────┤
│              Organizado por [Nome do Host]                 │
└────────────────────────────────────────────────────────────┘
```

#### Estados da Tela

**Waiting (Aguardando):**
```
┌────────────────────────────────────────┐
│          🎲 (animação pulse)           │
│      Aguardando Início...              │
│                                        │
│    Código de Convite: ABC123           │
└────────────────────────────────────────┘
```

**Finished (Finalizado):**
```
┌────────────────────────────────────────┐
│      Partida Finalizada!               │
│                                        │
│    Campeões da Partida:                │
│    🥇 João Silva                        │
│    🥈 Maria Santos                      │
└────────────────────────────────────────┘
```

#### Recursos Visuais

- Background gradiente roxo moderno
- Número atual: 12rem de tamanho
- Animação bounce-in ao sortear
- Auto-refresh: 3 segundos
- Cores semânticas:
  - Verde: números sorteados
  - Amarelo: prêmios concedidos
  - Cinza: pendentes

---

### 3️⃣ Tela do JOGADOR (Mobile/Desktop)

**Rota:** `/join/{invite_code}`  
**Componente:** `games-join.php`  
**Autenticação:** Obrigatória

#### Layout

```
┌─────────────────────────────────────────┐
│  Header                                 │
│  - Nome da partida                      │
│  - Rodada atual                         │
│  - Últimos sorteados (se habilitado)    │
├─────────────────────────────────────────┤
│  Minhas Cartelas                        │
│                                         │
│  ┌──── CARTELA #1 ────┐                │
│  │  [12] [45] [67]     │                │
│  │  [03] [🟡] [88]     │ ← Círculo      │
│  │  [21] [54] [09]     │   amarelo se   │
│  └─────────────────────┘   habilitado   │
│     5/9 marcados                        │
│                                         │
│  ┌──── CARTELA #2 ────┐                │
│  │  [15] [32] [51]     │                │
│  │  [07] [43] [69]     │                │
│  │  [28] [11] [77]     │                │
│  └─────────────────────┘                │
│     2/9 marcados                        │
├─────────────────────────────────────────┤
│  Prêmios                                │
│  1º Lugar - TV 50" (Disponível)         │
│  2º Lugar - R$ 100 (João Silva)         │
└─────────────────────────────────────────┘
```

#### Interações

- **Clicar em número:** Marca/desmarca
- **Número com círculo amarelo:** Você tem este número (se habilitado)
- **BINGO completo:** Alerta visual + notificar host
- **Swipe horizontal:** Navegar entre cartelas (se múltiplas)

#### Responsividade

- Mobile: 1 cartela por vez, swipe
- Tablet: 2 cartelas lado a lado
- Desktop: até 3 cartelas lado a lado

---

## 📦 Sistema de Pacotes

### Free (0 créditos)

```yaml
Custo: Grátis
Rodadas: 1
Jogadores: 10
Cartelas por jogador: 1
Tamanhos de cartela: 24 apenas
Features:
  - Sorteio manual
  - Visibilidade padrão
```

### Básico (10 créditos)

```yaml
Custo: 10 créditos
Rodadas: 3
Jogadores: 30
Cartelas por jogador: 2
Tamanhos de cartela: 15 ou 24
Features:
  - Sorteio manual ou automático
  - Controles de visibilidade
  - Prêmios ilimitados
```

### Premium (25 créditos)

```yaml
Custo: 25 créditos
Rodadas: Ilimitadas (999)
Jogadores: 100
Cartelas por jogador: 5
Tamanhos de cartela: 9, 15 ou 24
Features:
  - Todos os recursos
  - Suporte prioritário
  - Analytics avançados
```

---

## 💳 Sistema de Créditos

### Compra de Créditos

**Rota:** `/wallet`

#### Pacotes Disponíveis

| Pacote   | Créditos | Preço     | Valor/Crédito |
|----------|----------|-----------|---------------|
| Starter  | 10       | R$ 5,00   | R$ 0,50       |
| Popular  | 50       | R$ 20,00  | R$ 0,40       |
| Premium  | 150      | R$ 50,00  | R$ 0,33       |

#### Fluxo de Compra

```
1. Usuário acessa /wallet
2. Visualiza saldo atual
3. Clica em pacote desejado
4. Modal de confirmação abre
5. Confirma compra (simulada)
6. Créditos adicionados instantaneamente
7. Transação registrada em wallet_transactions
```

### Consumo de Créditos

```
DÉBITO (ao criar partida):
- Pacote Free: 0 créditos
- Pacote Básico: 10 créditos
- Pacote Premium: 25 créditos

CRÉDITO (reembolso automático se):
- Partida finalizada sem jogadores
- Partida finalizada sem vencedores
- Partida abandonada
```

### Histórico de Transações

**Rota:** `/wallet/transactions`

Campos registrados:
- Data/hora
- Descrição
- Tipo (credit/debit)
- Valor
- Saldo após transação
- Status (completed/pending/refunded)
- Relacionamento (Game ou Package)

---

## 🎮 Configurações de Visibilidade

### show_drawn_to_players

**Padrão:** `true`

- **true:** Jogadores veem últimos números sorteados e painel lateral
- **false:** Jogadores NÃO veem números (devem assistir à TV)

### show_player_matches

**Padrão:** `true`

- **true:** Círculo amarelo indica números que o jogador possui
- **false:** Sem indicador visual (jogador verifica manualmente)

### Modos Recomendados

```yaml
Modo Fácil (iniciantes):
  show_drawn_to_players: true
  show_player_matches: true

Modo Competitivo (experientes):
  show_drawn_to_players: false
  show_player_matches: false

Modo Híbrido:
  show_drawn_to_players: true
  show_player_matches: false
```

---

## 🛠️ Tecnologias

### Backend

- **Laravel 11:** Framework PHP
- **Livewire 4:** Componentes reativos
- **MySQL:** Banco de dados
- **Laravel Breeze:** Autenticação

### Frontend

- **Tailwind CSS:** Estilização
- **Alpine.js:** Interatividade client-side
- **Blade:** Template engine

### Destaques Técnicos

- **Livewire 4:**
  - `#[On]` attributes para eventos
  - `#[Computed]` para propriedades reativas
  - `#[Validate]` para validação inline
  - Polling nativo (wire:poll)
  
- **Arquitetura:**
  - Repository pattern
  - Service layer
  - Eloquent relationships otimizadas
  - Eager loading estratégico

---

## 📝 Próximos Passos

- [ ] Integração com WebSockets (Laravel Reverb)
- [ ] Gateway de pagamento real (Stripe/PagSeguro)
- [ ] Notificações push
- [ ] Chat entre jogadores
- [ ] Estatísticas e rankings
- [ ] Temas personalizáveis
- [ ] Exportação de relatórios
- [ ] API pública

---

## 📄 Licença

Este projeto é proprietário. Todos os direitos reservados.

---

## 👤 Autor

Desenvolvido por [Seu Nome]

**Contato:**
- Email: seu@email.com
- GitHub: @seu-usuario
- LinkedIn: /in/seu-perfil

---

## 🤝 Contribuindo

Este é um projeto privado, mas feedback é sempre bem-vindo! Entre em contato para sugestões.

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Abra uma issue no GitHub
2. Envie email para suporte@seubingo.com
3. Consulte a documentação completa

---

**Versão:** 1.0.0  
**Última atualização:** Fevereiro 2025
```

---

## Melhorias no README

✅ **Estrutura clara** com índice navegável  
✅ **Fluxo completo** passo a passo  
✅ **Diagramas ASCII** para visualização  
✅ **Exemplos de código** inline  
✅ **Tabelas** comparativas de pacotes  
✅ **Layout visual** das 3 telas  
✅ **Guia de instalação** detalhado  
✅ **Configurações** explicadas  
✅ **Roadmap** de próximas features
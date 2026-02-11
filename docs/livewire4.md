Aqui está um **guia objetivo e completo das principais mudanças do **Livewire 3 para o Livewire 4** — com explicações claras, exemplos de código e foco nas diferenças reais que você precisa saber hoje 👇 ([FilmoGaz][1])

---

# 🚀 Livewire 3 → Livewire 4 — Principais Mudanças (Markdown)

## 🧠 1) Nova Estrutura de Componentes — *View-First / Arquivo Único*

No **Livewire 4** você pode criar componentes com tudo **em um só arquivo**: lógica, Blade, CSS e até JavaScript — não é mais obrigatório separar class + view.

📌 Exemplo:

```php
{{-- resources/views/components/counter.wire.php --}}
@php
new class extends Livewire\Component {
    public $count = 0;

    public function increment() {
        $this->count++;
    }
};
@endphp

<div>
    <button wire:click="increment">+</button>
    <span>{{ $count }}</span>
</div>

<style>
/* CSS local ao componente */
</style>

<script>
// JS local opcional
</script>
```

**Livewire 3:** sempre class + Blade separado
**Livewire 4:** Single-File Component por padrão ([Medium][2])

---

## 🗂 2) Namespaces e Organização Melhorados

Livewire 4 recomenda uma estrutura mais lógica e alinhada ao Laravel:

* `pages::` → componentes de página
* `layouts::` → layouts
* componentes comuns em `resources/views/components`

📌 Exemplo de rota com namespace:

```php
Route::livewire('/posts/create', 'pages::post.create');
```

Isso **melhora a modularidade** do projeto. ([FilmoGaz][1])

---

## ⚡ 3) Islands — Partial Rendering Independente

O novo recurso **@island** permite renderizar partes isoladas da interface, carregadas e atualizadas independentemente do restante do componente — ótimo para dashboards e seções pesadas.

📌 Uso básico:

```blade
@island('revenue', lazy: true)
    @placeholder
        <x-revenue-skeleton />
    @endplaceholder

    <x-revenue-chart :data="$expensiveData" />
@endisland
```

**Vantagem:** desempenho melhor e UX mais responsivo. ([Wirelabs][3])

---

## 🧩 4) Slots e Suporte de Blade Melhorado

Livewire 4 agora suporta **slots como Blade tradicional**, incluindo slots nomeados.

📌 Exemplo:

```blade
<wire:modal>
    <x-slot:title>Confirmar ação</x-slot:title>
    Conteúdo aqui
</wire:modal>
```

Isso aproxima componentes Livewire do ecossistema de Blade. ([Wirelabs][3])

---

## ⚙️ 5) Configuração Atualizada

Algumas chaves no `config/livewire.php` foram renomeadas ou reorganizadas:

### Antes (v3):

```php
'layout' => 'components.layouts.app',
```

### Agora (v4):

```php
'component_layout' => 'layouts::app',
```

Outros exemplos:

* `lazy_placeholder` → `component_placeholder`
* Nova opção `smart_wire_keys` agora true por padrão ([Laravel][4])

---

## 🔥 6) Desempenho Geral e Blaze Compiler

Livewire 4 inclui grandes melhorias de performance por trás dos panos — graças ao novo **Blaze Compiler**:

* Renderização mais rápida
* Menos overhead em componentes
* Smart hydration

📌 Em benchmarks, algumas cargas ficam **até 10x mais rápidas**. ([Wirelabs][3])

---

## 🪟 7) Estados de Loading Automáticos

Agora componentes aplicam automaticamente atributos de loading (`data-loading`) sem precisar marcar manualmente `wire:loading` para cada botão.

📌 Uso com Tailwind:

```html
<button wire:click="save" class="btn" data-loading:class="opacity-50">
    Salvar
</button>
```

Isso torna estados de loading **mais simples e menos verbosos**. ([Wirelabs][3])

---

## 📦 8) Compatibilidade e Migração Suave

➡️ **Backward compatibility é prioridade.**
A maior parte dos componentes do Livewire 3 funciona em Livewire 4 sem refatoração.

📌 Para migrar:

```bash
composer require livewire/livewire:^4.0
php artisan optimize:clear
```

💡 Muitos ajustes são via config e nomes de métodos, raramente via lógica. ([Laravel][5])

---

## 🧪 9) Modificadores de `wire:model`

Em v4 alguns modificadores como `.blur` e `.change` mudaram comportamento e agora controlam **quando** o valor é sincronizado — se preciso manter modo antigo pode usar `.live` antes deles:

```html
wire:model.live.blur="campo"
```

Essa mudança dá mais controle ao sincronismo de estados. ([Laravel][4])

---

## 🧩 10) Componente Tradicional Ainda Suportado

Apesar do foco em Single-File Components, a forma clássica (separando class e view) continua **totalmente suportada**. Você decide o estilo que melhor serve ao projeto. ([Laravel News][6])

---

# 🧾 Resumo de Mudanças Rápido

| Recurso                | Livewire 3             | Livewire 4                  |
| ---------------------- | ---------------------- | --------------------------- |
| Componentes            | Class + view separados | Single-file por padrão      |
| Organ. de componentes  | flexível               | `pages::`, `layouts::`, etc |
| Renderização isolada   | ❌                      | ✔️ via `@island`            |
| Slots                  | limitado               | ✔️ como Blade               |
| Config defaults        | older                  | novos nomes/valores         |
| Performance            | boa                    | muito melhor (Blaze)        |
| Backward compatibility | sim                    | sim                         |
| wire:model behavior    | antigo                 | controlável com `.live`     |

---

Se quiser posso **comparar lado-a-lado com trechos de código reais de Livewire 3 vs Livewire 4**, além de exemplos práticos de migração de componentes antigos.

[1]: https://www.filmogaz.com/100623?utm_source=chatgpt.com "Discover What’s New in Livewire 4 Update - Filmogaz"
[2]: https://sadiqueali.medium.com/livewire-v4-release-starter-kit-updates-laravels-reactive-renaissance-979c919fedf5?utm_source=chatgpt.com "Livewire v4 Release & Starter Kit Updates: Laravel’s Reactive Renaissance | by Sadique Ali | Jan, 2026 | Medium"
[3]: https://wirelabs.io/blog/livewire-4-has-landed-the-full-stack-framework-that-got-a-speed-boost-and-a-makeover?utm_source=chatgpt.com "⚡ Livewire 4 Has Landed"
[4]: https://livewire.laravel.com/docs/4.x/upgrading?utm_source=chatgpt.com "Upgrade Guide | Laravel Livewire"
[5]: https://livewire.laravel.com/docs/upgrading?utm_source=chatgpt.com "Upgrade Guide | Laravel Livewire"
[6]: https://laravel-news.com/everything-new-in-livewire-4?utm_source=chatgpt.com "Everything new in Livewire 4 - Laravel News"

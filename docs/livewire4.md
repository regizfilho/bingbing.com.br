````md
# Livewire 3 → Livewire 4 — Diferenças com Exemplos de Código

Resumo **completo, direto e técnico**, incluindo **todas as mudanças relevantes** + **exemplos reais**.

---

## 1️⃣ Estrutura de Componentes (Single-File vs Multi-File)

### Livewire 3 (Multi-file obrigatório)

```php
// app/Livewire/Counter.php
namespace App\Livewire;

use Livewire\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }

    public function render()
    {
        return view('livewire.counter');
    }
}
````

```blade
<!-- resources/views/livewire/counter.blade.php -->
<div>
    <span>{{ $count }}</span>
    <button wire:click="increment">+</button>
</div>
```

---

### Livewire 4 (Single-File Component — padrão)

```php
<?php

use Livewire\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }
}
?>

<div>
    <span>{{ $count }}</span>
    <button wire:click="increment">+</button>
</div>
```

✅ Blade + PHP + JS + CSS no mesmo arquivo
✅ MFC ainda funciona

---

## 2️⃣ Slots (inexistente no v3)

### Livewire 4 — Componente com slot

```php
<?php

use Livewire\Component;

class Card extends Component {}
?>

<div {{ $attributes->merge(['class' => 'border p-4 rounded']) }}>
    <header>{{ $header }}</header>
    <main>{{ $slot }}</main>
</div>
```

Uso:

```blade
<livewire:card class="bg-white">
    <x-slot name="header">Título</x-slot>
    Conteúdo aqui
</livewire:card>
```

❌ Livewire 3 não suporta slots.

---

## 3️⃣ @island — Renderização Parcial (novo no v4)

### Livewire 4

```blade
<div>
    <h1>Dashboard</h1>

    @island
        <livewire:heavy-report />
    @endisland
</div>
```

✅ Apenas o bloco dentro de `@island` re-renderiza
❌ Livewire 3 sempre re-renderiza o componente inteiro

---

## 4️⃣ Loading States Automáticos

### Livewire 4

```blade
<button wire:click="save" class="btn">
    <span data-loading.remove>Salvar</span>
    <span data-loading>Salvando...</span>
</button>
```

❌ No v3 precisava de:

```blade
<span wire:loading.remove>Salvar</span>
<span wire:loading>Salvando...</span>
```

---

## 5️⃣ Scripts e Styles Dentro do Componente (novo)

### Livewire 4

```php
<?php use Livewire\Component; ?>

<div>
    <button wire:click="toggle">Toggle</button>
</div>

<script>
    console.log('JS do componente');
</script>

<style>
    button { color: red; }
</style>
```

❌ Livewire 3 exige assets externos.

---

## 6️⃣ wire:transition (mudança de API)

### Livewire 3

```blade
<div wire:transition.opacity.scale.duration.300ms>
    Conteúdo
</div>
```

### Livewire 4

```blade
<div wire:transition>
    Conteúdo
</div>
```

✔ Agora usa **View Transitions API do browser**
❌ Modificadores removidos

---

## 7️⃣ Routing de Componentes

### Livewire 3

```php
Route::get('/counter', Counter::class);
```

### Livewire 4 (padrão)

```php
Route::livewire('/counter', 'pages::counter');
```

Ou:

```php
Route::livewire('/counter', Counter::class);
```

---

## 8️⃣ Organização de Pastas (nova convenção)

### Livewire 4 (padrão)

```
app/Livewire/Pages/Dashboard.php   → pages::dashboard
app/Livewire/Layouts/App.php      → layouts::app
app/Livewire/Components/Button.php
```

Livewire 3 não impunha padrão.

---

## 9️⃣ Volt (mudança de namespace)

### Livewire 3

```php
use Livewire\Volt\Component;
```

### Livewire 4

```php
use Livewire\Component;
```

---

## 🔟 Testes

### Livewire 3

```php
Livewire::test(Counter::class)
    ->call('increment')
    ->assertSet('count', 1);
```

### Livewire 4 (igual + novos helpers)

```php
Livewire::test('pages::counter')
    ->call('increment')
    ->assertSee('1');
```

Novo:

```php
Livewire::visit('/counter')->assertSee('0');
```

---

## 1️⃣1️⃣ Polling Não Bloqueante

### Livewire 4

```blade
<div wire:poll.5s>
    {{ now() }}
</div>
```

✔ Não bloqueia interações
❌ No v3 podia travar requisições

---

## 1️⃣2️⃣ Attribute Forwarding (novo)

### Livewire 4

```blade
<livewire:button class="bg-red-500" />
```

```blade
<button {{ $attributes }}>
    {{ $slot }}
</button>
```

❌ No v3 precisava mapear manualmente.

---

## 📊 Resumo Comparativo

| Recurso                    | Livewire 3 | Livewire 4 |
| -------------------------- | ---------- | ---------- |
| Single-file components     | ❌          | ✅          |
| Slots                      | ❌          | ✅          |
| @island (render parcial)   | ❌          | ✅          |
| Loading automático         | ❌          | ✅          |
| Scripts/styles inline      | ❌          | ✅          |
| View Transitions nativas   | ❌          | ✅          |
| Organização por namespaces | ❌          | ✅          |
| Performance (Blaze engine) | ❌          | ✅          |
| Attribute forwarding       | ❌          | ✅          |
| Poll não bloqueante        | ❌          | ✅          |

---

## 🧠 Impacto Técnico Real

Livewire 4:
✔ Reduz arquivos
✔ Melhora performance perceptível
✔ Permite layout composável real (slots + islands)
✔ Simplifica estados de loading
✔ Aproxima DX de frameworks modernos (React/Vue)

---

Se quiser, posso gerar:

* Guia de migração automática 3 → 4
* Refatoração real de um componente seu
* Boilerplate SaaS Livewire 4 profissional

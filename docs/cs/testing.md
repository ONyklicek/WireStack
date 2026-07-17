---
order: 70
---

# Testování

Komponenty Wire jsou prosté PHP objekty, které se vykreslují do HTML, takže se
snadno testují. Existují tři užitečné úrovně:

| Úroveň | Použití pro | Potřebuje Livewire? |
|-------|---------|-----------------|
| **Samostatné** | Validační pravidla, save logika, konfigurace | Ne |
| **Livewire** | Vazba stavu, akce, chyby, celý cyklus requestu | Ano |
| **Unit** | API a vykreslený výstup jednoho pole/sloupce | Ne |

Balíčky jsou testovány pomocí [Pest](https://pestphp.com). Příklady níže používají
Pest syntaxi, ale všechno funguje stejně v čistém PHPUnit.

---

## Testování formulářů samostatně

`Form` funguje bez Livewire, což dělá z validace a save logiky nejrychlejší věc
k testování. `validate()` vrací zvalidovaná data nebo vyhodí
`Illuminate\Validation\ValidationException`.

```php
use Illuminate\Validation\ValidationException;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

it('validates required fields', function () {
    $data = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email'])->required(),
        ])
        ->state(['name' => 'John', 'email' => 'john@example.com'])
        ->validate();

    expect($data)->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

it('rejects a missing required field', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')->required()])
        ->state(['name' => '']);

    expect(fn () => $form->validate())->toThrow(ValidationException::class);
});
```

Asertujte na posbíraná pravidla a stav bez spuštění validace:

```php
$form = Form::make()
    ->statePath('data')
    ->schema([TextInput::make('name')->required()->maxLength(255)]);

expect($form->getValidationRules())->toHaveKey('data.name');
```

Otestujte save cestu proti databázi tím, že formuláři dáte model:

```php
use App\Models\User;

it('creates a user on save', function () {
    Form::make()
        ->model(User::class)
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ])
        ->state(['name' => 'Jane', 'email' => 'jane@example.com'])
        ->save();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
```

Pro test edit módu použijte `model($instance)` místo `model(User::class)`
(`save()` volá `update()`) a `isCreating()` / `isEditing()` pro assert módu.

---

## Testování formulářů v Livewire

Chcete-li protáhnout vazbu stavu, save akci a validační chyby tak, jak na ně
narazí uživatel, mountněte hostitelskou komponentu pomocí `Livewire::test()`.
Stav žije pod `statePath` formuláře, takže nastavujete a asertujete vnořené
klíče jako `data.name`.

```php
use Livewire\Livewire;

it('shows a validation error for a missing name', function () {
    Livewire::test(CreateUser::class)
        ->set('data.email', 'jane@example.com')
        ->call('save')
        ->assertHasErrors('data.name');
});

it('saves when the form is valid', function () {
    Livewire::test(CreateUser::class)
        ->set('data.name', 'Jane')
        ->set('data.email', 'jane@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertOk();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
```

Hostitelská komponenta je jen Livewire komponenta používající `WithForms`:

```php
use Livewire\Component;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->model(User::class)->schema([ // [tl! focus:start]
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ]); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
```

Repeater a další field akce jsou Livewire volání, takže je testujte pomocí
`->call()`:

```php
Livewire::test(CreateUser::class)
    ->call('addRepeaterItem', 'data.contacts')
    ->assertCount('data.contacts', 1);
```

---

## Testování tabulek v Livewire

Tabulka je Livewire komponenta používající `WithTable`. Její UI stav (hledání,
filtry, řazení, stránkování) žije na syntetizované vlastnosti `tableState`,
takže ji řídíte nastavováním vnořených cest jako `tableState.search`. Řádkové
akce běží přes `executeTableAction($recordKey, $actionName)`.

```php
use Livewire\Livewire;

it('lists and searches records', function () {
    Invoice::factory()->create(['number' => 'INV-100']);
    Invoice::factory()->create(['number' => 'INV-200']);

    Livewire::test(InvoicesTable::class)
        ->assertOk()
        ->assertSee('INV-100')
        ->set('tableState.search', 'INV-100')
        ->assertSee('INV-100')
        ->assertDontSee('INV-200');
});

it('runs a row action', function () {
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    Livewire::test(InvoicesTable::class)
        ->call('executeTableAction', (string) $invoice->getKey(), 'publish');

    expect($invoice->fresh()->status)->toBe('published');
});
```

Hromadné akce používají `executeBulkAction($actionName)` po výběru řádků a akce,
které otevírají modal, jdou přes `openActionModal()` a pak `submitActionModal()`.
Pokud assert neodpovídá, vypište komponentu pomocí `->dump()` a zkontrolujte živý
`tableState`.

---

## Unit test vlastního pole

Když píšete [vlastní pole](forms/custom-fields.md), otestujte jeho API a
vykreslený výstup přímo — Livewire není potřeba.

```php
use App\Forms\Components\MoneyInput;

it('exposes its configuration and renders the currency', function () {
    $field = MoneyInput::make('price')->currency('EUR')->decimals(2);

    expect($field->getCurrency())->toBe('EUR')
        ->and($field->getStateType())->toBe('int')
        ->and((string) $field->toHtml())->toContain('EUR');
});
```

Stejný přístup funguje pro vlastní sloupce, filtry a akce: postavte objekt,
zavolejte jeho settery a asertujte na jeho gettery nebo `toHtml()`.

---

## Testování pluginů

Instancujte `PluginManager` přímo pro test registrace, bootu a hooků. Kompletní
vzor viz [Core Pluginy → Testování pluginů](core/plugins.md#testovani-pluginu).

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

it('adds updated_by before save', function () {
    $manager = new PluginManager();
    $manager->register(new FormAuditPlugin());

    $payload = $manager->runHook('form.saving', ['data' => ['name' => 'Jane']]);

    expect($payload['data'])->toHaveKey('updated_by');
});
```

---

## Spuštění sady

```bash
composer test            # všechno
composer test:core
composer test:forms
composer test:table
composer test:sortable

# Cross-package runtime, stav, makra, pluginy
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

Když měníte jeden balíček, spusťte nejdřív **vlastnící balíček**, pak
**navazující** balíčky a Integration sadu, když se změnil stav, rendering, makra
nebo zapojení pluginů.

---

## Viz také

- [Rozšíření formulářů](forms/custom-fields.md) — stavba polí, která testujete
- [Životní cyklus ukládání](forms/save-lifecycle.md) — hooky, kterými `save()` prochází
- [Core Pluginy](core/plugins.md) — testování hooků a pluginů

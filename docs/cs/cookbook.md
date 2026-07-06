---
order: 80
---

# Kuchařka

Úlohově zaměřené recepty postavené na veřejném API. Každý je samostatný —
zkopírujte ho do Livewire komponenty používající `WithForms` (nebo `WithTable`)
a upravte.

| Chci… | Recept |
|------------|--------|
| Zobrazit pole jen když má jiné pole hodnotu | [Podmíněná pole](#conditional-fields) |
| Transformovat data před uložením | [Hashovat heslo při uložení](#hash-a-password-on-save) |
| Načíst existující záznam do formuláře | [Editovat existující záznam](#edit-an-existing-record) |
| Nahradit výchozí perzistenci | [Vlastní save logika](#custom-save-logic) |
| Spustit side efekty po úspěšném uložení | [Side efekty po uložení](#side-effects-after-save) |
| Zúžit tabulku na aktuálního uživatele/tenanta | [Zúžit dotaz tabulky](#scope-a-table-query) |
| Předvyplnit výchozí hodnoty z requestu nebo auth uživatele | [Dynamické výchozí hodnoty](#dynamic-defaults) |

---

<a id="conditional-fields"></a>
## Podmíněná pole

Nastavte řídicí pole jako `live()`, aby Livewire při změně překreslil, pak čtěte
stav formuláře v closuře `visible()`. Stav žije na vlastnosti komponenty svázané
přes `statePath`, takže closura definovaná v `form()` může číst `$this->data`.

```php
public ?array $data = [];

public function form(Form $form): Form
{
    return $form->statePath('data')->schema([
        Select::make('account_type')
            ->options(['personal' => 'Personal', 'business' => 'Business'])
            ->live()
            ->required(),

        TextInput::make('company_name')
            ->label('Company name')
            ->visible(fn () => ($this->data['account_type'] ?? null) === 'business')
            ->required(),
    ]);
}
```

Closura je arrow funkce deklarovaná uvnitř `form()`, takže její `$this` je
Livewire komponenta. Pro opak použijte `hidden()`, nebo vraťte libovolný boolean
výraz pro složitější pravidla.

---

<a id="hash-a-password-on-save"></a>
## Hashovat heslo při uložení

`mutateDataBeforeSave()` transformuje pole zvalidovaných dat těsně před
perzistencí — správné místo pro hashování, normalizaci nebo injektování polí.

```php
use Illuminate\Support\Facades\Hash;

$form
    ->model(User::class)
    ->schema([
        TextInput::make('email')->email()->required(),
        TextInput::make('password')->password()->required()->rules(['confirmed']),
    ])
    ->mutateDataBeforeSave(fn (array $data): array => [
        ...$data,
        'password' => Hash::make($data['password']),
    ]);
```

Pro aplikaci stejného pravidla na **každý** formulář v aplikaci použijte místo
toho plugin hook `form.saving` — viz [Core Pluginy](core/plugins.md#hook-system).

---

<a id="edit-an-existing-record"></a>
## Editovat existující záznam

Předejte **instanci** modelu (ne řetězec třídy) a naplňte z ní formulář. V edit
módu `save()` volá `update()` na záznamu.

```php
public User $user;

public ?array $data = [];

public function mount(User $user): void
{
    $this->user = $user;
    $this->form->fill($user->toArray());
}

public function form(Form $form): Form
{
    return $form
        ->statePath('data')
        ->model($this->user)        // instance ⇒ edit mód
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required(),
        ]);
}
```

`isEditing()` zde vrací `true`; bylo by `false`, kdybyste předali `User::class`.

---

<a id="custom-save-logic"></a>
## Vlastní save logika

Když výchozí create/update není to, co potřebujete — ukládání přes službu, API
nebo ne-Eloquent cíl — nahraďte perzistenci pomocí `using()`. Dostane zvalidovaná
data a její návratová hodnota se stane výsledkem save.

```php
$form
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->using(function (array $data) {
        return app(UserProvisioner::class)->create($data);
    });
```

Validace, `mutateDataBeforeSave()`, `beforeSave()` a `afterSave()` stále běží;
nahrazuje se pouze krok perzistence.

---

<a id="side-effects-after-save"></a>
## Side efekty po uložení

`afterSave()` dostane uložený záznam — použijte ho pro notifikace, události nebo
práci s relacemi, která potřebuje perzistovaný model.

```php
$form
    ->model(Order::class)
    ->schema([/* … */])
    ->afterSave(function ($record): void {
        OrderPlaced::dispatch($record);
        $record->customer->notify(new OrderConfirmation($record));
    });
```

Kompletní pořadí callbacků viz [Životní cyklus ukládání](forms/save-lifecycle.md).

---

<a id="scope-a-table-query"></a>
## Zúžit dotaz tabulky

Omezte jednu tabulku na aktuálního uživatele nebo tenanta pomocí
`modifyQueryUsing()`. Běží jako součást query pipeline tabulky.

```php
use Illuminate\Database\Eloquent\Builder;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()))
        ->columns([/* … */]);
}
```

Pro aplikaci stejného zúžení napříč **mnoha** tabulkami ho přesuňte do query pipe
pluginu nebo hooku `table.querying` — viz
[Core Pluginy → Query Pipes](core/plugins.md#query-pipes).

---

<a id="dynamic-defaults"></a>
## Dynamické výchozí hodnoty

`default()` přijímá closuru resolvovanou v čase renderu, takže nový (create-mód)
formulář může naplnit hodnoty z auth, requestu nebo config.

```php
$form
    ->model(Post::class)
    ->schema([
        TextInput::make('title')->required(),
        Hidden::make('author_id')->default(fn () => auth()->id()),
        Select::make('status')->options(Status::options())->default('draft'),
    ]);
```

Výchozí hodnoty se aplikují jen když pole ještě nemá hodnotu, takže nepřepíší
editovaný záznam.

---

## Viz také

- [Přehled formulářů](forms/overview.md) — kompletní Form API
- [Životní cyklus ukládání](forms/save-lifecycle.md) — hooky v pořadí
- [Rozšíření formulářů](forms/custom-fields.md) — když recept potřebuje nové pole
- [Přehled tabulek](table/overview.md) — sloupce, filtry, akce
- [Core Pluginy](core/plugins.md) — pravidla pro celou aplikaci přes hooky a pipes

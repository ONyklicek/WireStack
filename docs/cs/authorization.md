---
order: 50
---

# Autorizace

Wire používá Laravel Gate a policies. Autorizace tak zůstává kompatibilní s nativními Laravel policies, Spatie Permission a balíčky, které registrují abilities do Gate.

## Sdílená pravidla komponent

Sloupce, filtry, akce, pole a widgety mohou používat sdílené autorizační metody, když komponenta podporuje viditelnost nebo autorizaci.

```php
Action::make('approve')
    ->label('Approve')
    ->authorize('approve')
    ->action(fn (Order $record) => $record->approve());

TextColumn::make('internal_note')
    ->label('Internal note')
    ->permission('orders.internal-notes.view');

SelectFilter::make('department_id')
    ->authorizeUsing(fn (User $user) => $user->is_admin);
```

Pořadí vyhodnocení:

| Pravidlo | Chování |
|------|----------|
| Žádná autorizace nakonfigurovaná | Povoleno |
| Žádný přihlášený uživatel | Zamítnuto |
| `authorizeUsing()` | Vlastní callback má prioritu |
| `authorize()` | Kontroluje Laravel Gate ability |
| `permission()` | Kontroluje permission řetězec přes Gate |

### Autorizace per záznam

Callback `authorizeUsing()` dostává přihlášeného uživatele a — kde ho surface má — **záznam řádku**, takže autorizaci lze zúžit per záznam:

```php
Action::make('approve')
    ->authorizeUsing(fn (User $user, $record) => $user->id === $record?->manager_id)
    ->action(fn (Order $record) => $record->approve());
```

Záznam je přítomný u **řádkových akcí**; je `null` u surface bez záznamu (strukturální viditelnost sloupce/filtru, pole, widgety), takže jednoargumentová closura `fn ($user) => …` zůstává platná všude.

Tohle řídí, zda celý sloupec/akce strukturálně **existuje** (vyhodnoceno jednou). Pro skrytí nebo redakci **jedné buňky na řádek** — např. zobrazit `salary` jen na záznamech, které uživatel smí vidět — použijte místo toho `visibleForRecord()` sloupce, který běží při renderu buňky se záznamem daného řádku:

```php
TextColumn::make('salary')
    ->visibleForRecord(fn ($record) => auth()->user()->can('viewSalary', $record));
```

## Policies tabulky

Zapněte kontroly policies na tabulce pomocí `authorize()`.

```php
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->authorize()
        ->columns([
            // ...
        ]);
}
```

Wire kontroluje tyto policy metody, když jsou potřeba:

| Schopnost tabulky | Policy ability |
|------------------|----------------|
| Vytvořit záznam | `create` |
| Aktualizovat záznam | `update` |
| Smazat záznam | `delete` |
| Zobrazit záznam | `view` |

## Přepisy tabulky

Přepisy použijte, když tabulka potřebuje pravidla odlišná od policy modelu.

```php
return $table
    ->model(Order::class)
    ->authorize()
    ->authorizeCreate(fn () => auth()->user()?->can('create', Order::class) ?? false)
    ->authorizeUpdate(fn (Order $record) => ! $record->is_locked)
    ->authorizeDelete(fn (Order $record) => $record->status === 'draft')
    ->authorizeView(fn (Order $record) => $record->tenant_id === auth()->user()?->tenant_id);
```

Každý přepis přijímá boolean nebo closuru.

## Inline editace

Editovatelné sloupce mohou pro inline editaci vyžadovat Gate ability.

```php
TextInputColumn::make('price')
    ->authorizeInline('orders.update-price');
```

Pokud uživatel neprojde kontrolou ability, sloupec zůstane viditelný, ale inline editace je zamítnuta.

## Akce

Akce lze skrýt nebo zamítnout pomocí Gate abilities, permission řetězců nebo vlastních callbacků.

```php
Action::make('refund')
    ->label('Refund')
    ->authorize('refund')
    ->visible(fn (Order $record) => $record->is_paid)
    ->requiresConfirmation()
    ->action(fn (Order $record) => $record->refund());
```

Pro jednoduché permission řetězce:

```php
Action::make('export')
    ->permission('orders.export')
    ->action(fn () => $this->exportTable());
```

## Formuláře

Formuláře mohou používat policies modelu pro create a update.

```php
use NyonCode\WireForms\Forms\Form;

public function form(Form $form): Form
{
    return $form
        ->model($this->user ?? User::class)
        ->authorize()
        ->schema([
            // ...
        ]);
}
```

Když je `authorize()` zapnuto:

| Stav formuláře | Policy ability |
|------------|----------------|
| Třída modelu nebo neuložený model | `create` |
| Existující instance modelu | `update` |

Při zamítnutí je formulář read-only a nelze ho uložit.

Pro vlastní pravidla formuláře:

```php
return $form
    ->model($this->user)
    ->authorizeUsing(fn (User $user) => $user->hasRole('editor'))
    ->schema([
        // ...
    ]);
```

## Sortable

Sortable operace by měly být chráněné v hoocích vaší Livewire komponenty.

```php
public function beforeRowsReordered(array $orderedIds): void
{
    $this->authorize('reorder', Task::class);
}
```

Lifecycle hooky viz [Sortable řazení řádků](sortable/row-sorting.md).

## Související dokumentace

| Dokument | Co pokrývá |
|----------|----------------|
| [Core Akce](core/actions.md) | Řádkové, hromadné, hlavičkové akce a modální akce |
| [Přehled tabulek](table/overview.md) | Nastavení tabulky a API na úrovni tabulky |
| [Přehled formulářů](forms/overview.md) | Nastavení formuláře a chování ukládání |
| [Audit Log](core/audit.md) | Záznam změn modelů po úspěšné autorizaci |

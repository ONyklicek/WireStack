# Repeater

`Repeater` spravuje opakované skupiny polí a umí perzistovat data `hasMany` relace.

## Základní použití

```php
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;

Repeater::make('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
```

## Režim relace

Použijte `relationship()`, když má repeater ukládat související záznamy.

```php
Repeater::make('contacts')
    ->relationship('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
    ->addable()
    ->deletable()
    ->reorderable()
```

## Limity a UX ovládání

```php
Repeater::make('contacts')
    ->minItems(1)
    ->maxItems(10)
    ->addButtonLabel('Add contact')
    ->collapsible()
    ->itemLabel(fn (array $state) => $state['name'] ?? null)   // pojmenované položky: „#1 Ada"
```

### Pojmenované položky

Ve výchozím stavu je každý blok položky nadepsán svým číslem (`#1`, `#2`, …).
`itemLabel()` přijme statický řetězec nebo closure nad stavem položky (a
indexem) a zobrazí název vedle čísla — hodí se pro identifikaci sbalených
položek. Label se překresluje v reaktivním cyklu položky, takže ho zkombinuj s
polem `->live()`, aby se aktualizoval, jak uživatel píše.

```php
Repeater::make('contacts')
    ->schema([TextInput::make('name')->live()])
    ->itemLabel(fn (array $state, int $index) => $state['name'] ?? "Contact #{$index}");
```

| Metoda | Účel |
|--------|---------|
| `addable()` | Povolit nové položky |
| `deletable()` | Povolit odebrání položky |
| `reorderable()` | Povolit manuální přeřazování |
| `collapsible()` | Nechat uživatele sbalit bloky položek |
| `collapsed()` | Začít položky sbalené |
| `minItems()` / `maxItems()` | Omezit velikost kolekce |
| `addable(bool)` | Povolit přidávání nových položek (výchozí `true`) |
| `deletable(bool)` | Povolit odebírání položek (výchozí `true`) |
| `reorderable(bool)` | Povolit drag-to-reorder (výchozí `false`) |
| `collapsible(bool)` | Povolit sbalování bloků položek |
| `collapsed(bool)` | Začít všechny položky sbalené (implikuje `collapsible`) |
| `minItems(int\|null)` | Minimální počet položek |
| `maxItems(int\|null)` | Maximální počet položek |
| `addButtonLabel(string\|null)` | Label na tlačítku přidat |
| `itemLabel(string\|Closure\|null)` | Název vedle čísla položky (`fn(array $state, int $index): ?string`) |
| `disabled(bool\|Closure)` | Znepřístupnit ovládání add/delete/reorder |
| `mutateRelationshipDataBeforeSaveUsing(Closure)` | Transformovat data položek před perzistencí |

## Reaktivita per položka

Reaktivní chování uvnitř repeateru se resolvuje **per položka**: `afterStateUpdated()`, live
validace, field akce, remote select hledání a podmíněná viditelnost čtou vlastní
state bag položky a `$get`/`$set` jsou zúžené na tu položku.

```php
Repeater::make('contacts')->schema([
    Select::make('type')->options(['email' => 'Email', 'other' => 'Other'])->live(),
    TextInput::make('other_detail')->visibleWhen('type', 'other'),
])
```

Zde se `other_detail` zobrazí jen v řádcích, jejichž vlastní `type` je `other` — přepnutí
selectu řádku 2 nikdy neovlivní řádek 1. Kompletní referenci accessorů viz
[Reaktivní pole](../reactive-fields.md).

## Kdy ho použít

`Repeater` použijte, když jeden formulář vlastní malou až střední kolekci souvisejících dětských záznamů a uživatel by je měl spravovat inline.

Pokud dětské záznamy potřebují nezávislé filtrování, stránkování nebo těžké workflow, dejte jim vlastní tabulku nebo obrazovku.

## Související dokumentace

- [Přehled formulářů](../overview.md)
- [Validace](../validation.md)

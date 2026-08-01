# CheckboxList

Více checkboxů z pole options.

```php
use NyonCode\WireForms\Components\CheckboxList;
```

## Použití

```php
CheckboxList::make('permissions')
    ->options([
        'create' => 'Create',
        'read'   => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
    ])
    ->columns(2)
    ->searchable()
    ->bulkToggleable()
```

## Dynamické options

```php
CheckboxList::make('roles')
    ->options(fn () => Role::pluck('name', 'id')->toArray())
```

## Options z enumu

Předejte třídu PHP enumu pro rozvinutí jeho case na options `value => label`. Labely pocházejí z
`getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`, jinak se
z názvu case udělá headline. Detaily viz [Select › Options z enumu](select.md#options-z-enumu).

```php
CheckboxList::make('permissions')->options(Permission::class)
```

## Vícesloupcový layout

```php
CheckboxList::make('features')
    ->options([...])
    ->columns(3)
```

## Hledání

```php
CheckboxList::make('permissions')
    ->options([...])
    ->searchable()
    ->searchPrompt('Filter permissions...')
```

## Hromadné přepnutí

```php
CheckboxList::make('permissions')
    ->bulkToggleable()
    ->selectAllLabel('Select All')
    ->deselectAllLabel('Deselect All')
```

## Seskupené options

Při použití `groups()` je každý klíč nadpisem skupiny a jeho hodnota je pole párů `value => label`.

```php
CheckboxList::make('permissions')
    ->groups([
        'Posts' => ['create_post' => 'Create', 'edit_post' => 'Edit', 'delete_post' => 'Delete'],
        'Users' => ['create_user' => 'Create', 'edit_user' => 'Edit'],
    ])
```

Volání `groups()` automaticky zapne seskupený layout. Můžete také zavolat `grouped()` explicitně.

## Varianty s přepínacími tlačítky

Když je seznam krátký, čtou se tytéž volby lépe jako řada přepínacích tlačítek
než jako sloupec zaškrtávátek. `segmented()` a `buttons()` vykreslí přesně ten
vzhled jako odpovídající varianty [Radio](radio.md) — jde o vícehodnotovou
polovinu téhož sdíleného slovníku, takže jednovýběrový a vícevýběrový ovládací
prvek vypadají stejně:

```php
CheckboxList::make('days')
    ->options(['mon' => 'Po', 'tue' => 'Út', 'wed' => 'St'])
    ->segmented()

CheckboxList::make('roles')
    ->options(['admin' => 'Admin', 'editor' => 'Editor'])
    ->buttons()
    ->inline()
    ->icons(['admin' => 'shield-check'])
    ->colors(['admin' => 'danger'])
```

Tyto varianty zobrazují jen volby: hledání, hromadné přepnutí, seskupení a
sloupce jsou výbava seznamu a neuplatní se.

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Seznam options nebo třída enumu (`value => label`) |
| `columns(int)` | int | Počet sloupců (výchozí `1`) |
| `searchable(bool)` | bool | Zapnout hledací box filtr-podle-labelu |
| `searchPrompt(string\|null)` | string | Placeholder hledacího inputu |
| `bulkToggleable(bool)` | bool | Zobrazit ovládání select-all / deselect-all |
| `selectAllLabel(string\|null)` | string | Label tlačítka select-all |
| `deselectAllLabel(string\|null)` | string | Label tlačítka deselect-all |
| `grouped(bool)` | bool | Zapnout seskupený layout |
| `groups(array\|Closure)` | array | Definice skupin (také zapíná seskupený layout) |
| `default(array\|Closure)` | array | Předvybrané hodnoty |
| `disabled(bool\|Closure)` | bool | Znepřístupnit všechny checkboxy |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

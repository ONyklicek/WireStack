# Tags

Volný tag input. Uživatel napíše text a potvrdí ho jako chip stisknutím Enter nebo čárky. Podporuje předdefinované návrhy, limity a režim relace.

```php
use NyonCode\WireForms\Components\Tags;
```

## Základní použití

```php
Tags::make('labels')
```

Stav je pole řetězců: `['php', 'laravel', 'vue']`.

## S návrhy

```php
Tags::make('skills')
    ->suggestions(['PHP', 'Laravel', 'Vue', 'React', 'TypeScript'])
```

Při `allowNew(false)` lze vybrat jen návrhy:

```php
Tags::make('category')
    ->suggestions(fn () => Category::pluck('name')->toArray())
    ->allowNew(false)
```

## Limity

```php
Tags::make('tags')
    ->minItems(1)
    ->maxItems(5)
```

## Rozdělovací klávesy

Ve výchozím stavu Enter a čárka potvrdí tag. V případě potřeby přepište:

```php
Tags::make('tags')
    ->splitKeys(['Enter', ' '])   // mezerou oddělené tagy
```

## Relace

```php
Tags::make('tags')
    ->relationship('tags', 'name')   // many-to-many
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `suggestions(array\|Closure)` | array | Předdefinované hodnoty zobrazené jako autocomplete |
| `splitKeys(array)` | array | Klávesy, které potvrdí input (výchozí `['Enter', ',']`) |
| `minItems(int\|null)` | int | Minimální počet tagů |
| `maxItems(int\|null)` | int | Maximální počet tagů |
| `allowNew(bool)` | bool | Povolit tagy, které nejsou v návrzích (výchozí `true`) |
| `allowDuplicates(bool)` | bool | Povolit stejný tag dvakrát (výchozí `false`) |
| `relationship(?string, ?string)` | — | Název many-to-many relace a title atribut |
| `placeholder(string\|Closure)` | string | Placeholder inputu |
| `disabled(bool\|Closure)` | bool | Znepřístupnit input |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `live()` | — | Spustit Livewire update po každé změně tagu |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

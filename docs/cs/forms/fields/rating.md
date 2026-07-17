# Rating

Pole hvězdičkového hodnocení s konfigurovatelným max počtem hvězd, přesností na půl hvězdy a barvou.

```php
use NyonCode\WireForms\Components\Rating;
```

## Základní použití

```php
Rating::make('score')
    ->max(5)
    ->default(0)
```

## Půlhvězdy

```php
Rating::make('rating')
    ->allowHalf()    // umožňuje kroky po 0.5: 1, 1.5, 2, 2.5 …
```

## Vlastní max

```php
Rating::make('priority')
    ->max(3)         // 3-hvězdičková škála
```

## Barvy

```php
Rating::make('satisfaction')
    ->color('primary')   // libovolná barva palety (výchozí 'primary')
```

## Bez možnosti vyčištění

```php
Rating::make('score')
    ->clearable(false)   // kliknutí na aktivní hvězdu ji už neresetuje
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `max(int)` | int | Počet hvězd (výchozí `5`) |
| `allowHalf(bool)` | bool | Zapnout výběr půlhvězdy (výchozí `false`) |
| `color(string)` | string | Barva vyplněné hvězdy — libovolná barva palety (viz [Colors](../../core/foundation.md#barvy)) |
| `clearable(bool)` | bool | Kliknutí na aktivní hvězdu resetuje na 0 (výchozí `true`) |
| `default(int\|float\|Closure)` | number | Předvyplněná hodnota |
| `disabled(bool\|Closure)` | bool | Znepřístupnit hodnocení |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při kliknutí |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

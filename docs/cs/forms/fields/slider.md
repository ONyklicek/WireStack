# Slider

Vizuální slider rozsahu pro číselné hodnoty s konfigurovatelným min, max a krokem.

```php
use NyonCode\WireForms\Components\Slider;
```

## Základní použití

```php
Slider::make('volume')
    ->min(0)
    ->max(100)
    ->default(50)
```

## S jednotkami

Použijte `prefix()` / `suffix()` pro přidání labelu jednotky ke koncovým bodům rozsahu i k odznaku aktuální hodnoty:

```php
Slider::make('discount')
    ->min(0)
    ->max(100)
    ->suffix('%')
    ->default(10)
```

```php
Slider::make('price')
    ->min(0)
    ->max(10000)
    ->step(100)
    ->prefix('CZK ')
    ->showValue()
```

## Desetinný krok

```php
Slider::make('opacity')
    ->min(0.0)
    ->max(1.0)
    ->step(0.05)
    ->default(1.0)
```

## Skrýt odznak hodnoty

```php
Slider::make('threshold')
    ->showValue(false)
```

## Vlastní barva

Dráha je vyplněná až po aktuální hodnotu a jezdec je obarven, aby ladil.
Barva výplně je ve výchozím stavu primary motivu; přepište ji libovolnou CSS barvou:

```php
Slider::make('volume')
    ->color('#f59e0b')          // hex

Slider::make('health')
    ->color('rgb(16 185 129)')  // rgb
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `min(int\|float)` | number | Minimální hodnota (výchozí `0`) |
| `max(int\|float\|Closure)` | number | Maximální hodnota (výchozí `100`) |
| `step(int\|float)` | number | Krok inkrementu (výchozí `1`) |
| `showValue(bool)` | bool | Zobrazit odznak s aktuální hodnotou (výchozí `true`) |
| `color(?string)` | string | CSS barva výplně/jezdce (výchozí primary motivu) |
| `prefix(string)` | string | Prefix jednotky u min/max labelů a odznaku hodnoty |
| `suffix(string)` | string | Suffix jednotky u min/max labelů a odznaku hodnoty |
| `default(int\|float\|Closure)` | number | Předvyplněná hodnota |
| `disabled(bool\|Closure)` | bool | Znepřístupnit slider |
| `live()` | — | Spustit Livewire update při každém pohybu |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).

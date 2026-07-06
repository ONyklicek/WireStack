---
order: 31
nav: false
---

# NumberRangeFilter

Filtr číselného rozsahu s min/max inputy. Vykreslí dva číselné inputy.

```php
use NyonCode\WireTable\Filters\NumberRangeFilter;
```

## Základní použití

```php
NumberRangeFilter::make('price')
    ->min(0)
    ->max(10000)
    ->step(0.01)
```

## Celočíselný rozsah

```php
NumberRangeFilter::make('age')
    ->min(18)
    ->max(100)
    ->step(1)
```

## Vlastní popisky

```php
NumberRangeFilter::make('salary')
    ->min(0)
    ->max(500000)
    ->step(1000)
    ->minLabel('Minimum Salary')
    ->maxLabel('Maximum Salary')
```

## Chování rozsahu

| min | max | Podmínka |
|-----|-----|-----------|
| nastaveno | null | `WHERE column >= min` |
| null | nastaveno | `WHERE column <= max` |
| nastaveno | nastaveno | `WHERE column >= min AND column <= max` |
| null | null | Žádný filtr neaplikován |

## API NumberRangeFilter

```php
->min(float $min)                    // minimální povolená hodnota
->max(float $max)                    // maximální povolená hodnota
->step(float $step)                  // krok inkrementu inputu
->minLabel(string $label)            // popisek min inputu (výchozí: 'Min')
->maxLabel(string $label)            // popisek max inputu (výchozí: 'Max')
```

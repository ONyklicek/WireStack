---
order: 31
nav: false
---

# NumberRangeFilter

Numeric range filter with min/max inputs. Renders two number inputs.

```php
use NyonCode\WireTable\Filters\NumberRangeFilter;
```

## Basic Usage

```php
NumberRangeFilter::make('price')
    ->min(0)
    ->max(10000)
    ->step(0.01)
```

## Integer Range

```php
NumberRangeFilter::make('age')
    ->min(18)
    ->max(100)
    ->step(1)
```

## Custom Labels

```php
NumberRangeFilter::make('salary')
    ->min(0)
    ->max(500000)
    ->step(1000)
    ->minLabel('Minimum Salary')
    ->maxLabel('Maximum Salary')
```

## Range Behavior

| min | max | Condition |
|-----|-----|-----------|
| set | null | `WHERE column >= min` |
| null | set | `WHERE column <= max` |
| set | set | `WHERE column >= min AND column <= max` |
| null | null | No filter applied |

## NumberRangeFilter API

```php
->min(float $min)                    // minimum allowed value
->max(float $max)                    // maximum allowed value
->step(float $step)                  // input step increment
->minLabel(string $label)            // label for min input (default: 'Min')
->maxLabel(string $label)            // label for max input (default: 'Max')
```

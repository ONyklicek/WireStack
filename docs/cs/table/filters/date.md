---
order: 31
nav: false
---

# DateFilter

Filtr data s režimem jednoho data nebo rozsahu dat. Vykreslí nativní date input(y).

```php
use NyonCode\WireTable\Filters\DateFilter;
```

## Jedno datum

Výchozí režim vykreslí jeden date input a shoduje se s tím přesným dnem.

```php
DateFilter::make('created_at')
// Aplikuje: WHERE DATE(created_at) = '2024-01-15'
```

## Rozsah dat

Zavolejte `range()` pro vykreslení dvou date inputů („from" a „to"). Uživatel může
vyplnit kteroukoli stranu, takže otevřené rozsahy fungují bez extra konfigurace.

```php
DateFilter::make('created_at')
    ->range()
// Vykreslí dva date inputy.
// Oba nastavené:  WHERE DATE(created_at) >= from AND <= to
// Jen from:       WHERE DATE(created_at) >= from
// Jen to:         WHERE DATE(created_at) <= to
```

## Vlastní popisky

V režimu rozsahu se popisky použijí jako placeholdery inputů.

```php
DateFilter::make('period')
    ->column('created_at')
    ->range()
    ->fromLabel('Created after')
    ->toLabel('Created before')
```

## Měsíc + rok

Zavolejte `month()` pro filtrování podle celého měsíce místo přesného dne. Vykreslí
nativní výběr měsíce (`<input type="month">`) a shoduje se s každým záznamem ve
vybraném měsíci:

```php
DateFilter::make('billed_at')
    ->month()
// Hodnota "2026-06" aplikuje: WHERE YEAR(billed_at) = 2026 AND MONTH(billed_at) = 6
```

Zkombinujte s [`subRows()`](relationships.md#filtrovani-podle-hodnot-podradku) pro filtrování rodičů podle
měsíce jejich dětských záznamů.

## Omezení data

```php
DateFilter::make('birth_date')
    ->minDate('1900-01-01')
    ->maxDate(now()->format('Y-m-d'))
```

## API DateFilter

```php
->range(bool $range = true)         // dva inputy (from/to) místo jednoho
->month(bool $month = true)         // výběr měsíce, shoduje se s celým měsícem
->fromLabel(string $label)          // placeholder "from" (výchozí: 'From')
->toLabel(string $label)            // placeholder "to" (výchozí: 'To')
->minDate(string $date)             // min volitelné datum
->maxDate(string $date)             // max volitelné datum
```

## Chování rozsahu

| from | to | Podmínka |
|------|----|-----------|
| nastaveno | null | `WHERE DATE(column) >= from` |
| null | nastaveno | `WHERE DATE(column) <= to` |
| nastaveno | nastaveno | `WHERE DATE(column) >= from AND <= to` |
| null | null | Žádný filtr neaplikován |

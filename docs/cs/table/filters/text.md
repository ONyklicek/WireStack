---
order: 31
nav: false
---

# TextFilter

Textový filtr s konfigurovatelným SQL operátorem. Ve výchozím stavu substringové
`LIKE`, takže funguje jako vyhledávací pole omezené na jeden sloupec, plus
operátory prefix / suffix / přesná shoda / porovnání. Je to zároveň engine za
textovým filtrem hlavičky sloupce (`->filterable()`).

```php
use NyonCode\WireTable\Filters\TextFilter;
```

## Základ (substringová shoda)

```php
TextFilter::make('name')
// WHERE name LIKE '%value%'
```

## Operátory

```php
TextFilter::make('sku')
    ->operator('starts_with')   // WHERE sku LIKE 'value%'

TextFilter::make('email')
    ->operator('=')             // přesná shoda

TextFilter::make('age')
    ->operator('>=')            // číselné / lexikální porovnání
```

Podporováno: `like` (výchozí), `starts_with`, `ends_with`, `equals` / `=`, `!=`,
`>`, `>=`, `<`, `<=`.

## Vlastní query logika

```php
TextFilter::make('title')
    ->query(fn (Builder $query, $value) => $query->where('title', 'like', "%{$value}%"))
```

## TextFilter API

```php
->operator(string $operator)        // výchozí: 'like'
->debounce(?int $ms)                // debounce živého vstupu
->query(Closure $fn)                // vlastní query: fn(Builder $q, $value)
```

Porovnání prochází kanonickým `QueryPlanner`em (operátor se mapuje na `LIKE` /
porovnávací klauzuli), takže joiny a kvalifikace sloupců se řeší stejně jako u
každého jiného filtru.

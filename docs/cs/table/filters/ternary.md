---
order: 31
nav: false
---

# TernaryFilter

Trojstavový filtr: Yes / No / All. Ideální pro boolean sloupce a relace „má/nemá".

Vykresluje se přes stejný combobox jako [`SelectFilter`](select.md) a `Select`
field ve formulářích, takže otevřený boolean filtr vypadá stejně jako kterýkoli
jiný select. „All" je placeholder — jeho výběrem se filtr zruší. Nativní `<select>`
prohlížeče je opt-in přes [`->native()`](#nativni-html-select).

```php
use NyonCode\WireTable\Filters\TernaryFilter;
```

## Základní boolean

```php
TernaryFilter::make('is_active')
// Ukazuje: All | Yes | No
// Yes: WHERE is_active = 1
// No: WHERE is_active = 0
```

## Nullable sloupec

```php
TernaryFilter::make('email_verified_at')
    ->nullable()
// Yes: WHERE email_verified_at IS NOT NULL
// No: WHERE email_verified_at IS NULL
```

## Vlastní popisky

```php
TernaryFilter::make('verified')
    ->label('Verification Status')
    ->trueLabel('Verified Only')
    ->falseLabel('Unverified Only')
```

## Vlastní logika dotazu

Použijte jediný callback `query()`; dostane builder a vybraný stav jako
**skutečný boolean** — `true` pro option „Ano", `false` pro „Ne". Volba „Vše"
filtr vypne, takže se callback s prázdným stavem nikdy nezavolá.

```php
TernaryFilter::make('has_orders')
    ->label('Has Orders')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->has('orders')
        : $query->doesntHave('orders'))
```

```php
TernaryFilter::make('overdue')
    ->label('Overdue')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->where('due_at', '<', now())
        : $query->where('due_at', '>=', now()))
```

Nejčastější důvod sáhnout po `query()` je filtrování podle relace, které se
přes `where(column, bool)` vyjádřit nedá:

```php
TernaryFilter::make('invoiced')
    ->label('Fakturováno')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->whereHas('invoice')
        : $query->whereDoesntHave('invoice'))
```

`nullable()` rozšiřuje větev „Ne" u **výchozího** dotazu. Callback `query()`
vlastní svůj dotaz, takže se tam neuplatní — callback se dozví, která strana
byla vybraná, a rozhodne sám, jak se má `NULL` chovat.

> **Změna v 1.13.0** — callback dřív dostával syrový stav selectu
> (`'true'` / `'false'`), takže `$value ? … : …` větvil podle truthy stringu a
> obě volby vracely stejné řádky. Nově dostane skutečný `bool`. Callback, který
> porovnává se stringem (`$value === '1'`, `$value === 'true'`), je potřeba
> upravit; syrový stav zůstává dostupný jako volitelný třetí argument.

## Nativní HTML select

```php
TernaryFilter::make('is_active')
    ->native()                      // nativní <select> element prohlížeče (rychlejší render)
```

Odhlásí filtr ze sdíleného comboboxu, takže přestane odpovídat ostatním selectům.
Používej jen tam, kde je cena renderu důležitější než jednotný vzhled.

## API TernaryFilter

```php
->trueLabel(string $label)          // výchozí: 'Yes'
->falseLabel(string $label)         // výchozí: 'No'
->allLabel(string $label)           // placeholder pro option „bez filtru"
->nullable(bool $nullable = true)   // „false" také odpovídá IS NULL
->native(bool $native = true)       // opt-in nativní <select> prohlížeče (výchozí: false)
->query(Closure $fn)                // vlastní dotaz: fn(Builder $q, bool $value)
```

## Hodnoty stavu

Select odesílá klíč option; callbacky `query()` i výchozí dotaz pracují
s normalizovaným booleanem, takže se podle transportní podoby nikdy nevětví.

| Stav UI | Odeslaný stav | `$value` v `query()` | Výchozí chování |
|---------|---------------|----------------------|-----------------|
| All | `''` / `null` | *(nezavolá se)* | Žádný filtr |
| Yes | `'true'` | `true` | `WHERE column = 1` |
| No | `'false'` | `false` | `WHERE column = 0` (nebo `= 0 OR IS NULL` pokud nullable) |

Stav načtený z URL nebo nastavený programově může přijít i jako `'1'`/`'0'`,
`1`/`0` nebo skutečný bool — všechny se přijímají a normalizují stejně.

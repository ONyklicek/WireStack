---
order: 31
summary: Free text compared one way you choose, or a choice of comparisons the user picks — contains, is, starts with, their negatives and "is empty".
---

# TextFilter

Free-text filter. Substring `LIKE` by default, so it works as a scoped search box
for a single column; one call fixes another comparison (prefix, exact, …), and
`->operators()` hands the choice to the user with a picker beside the input. It
is also the engine behind a column header's `->filterable()` text filter.

```php
use NyonCode\WireTable\Filters\TextFilter;
```

## How It Works

**One comparison, or a choice.** `->operator()` fixes the comparison the filter
makes; the user sees only the text box. `->operators()` draws a picker beside it
with the operators you list (or all of them), and the state becomes
`{value, operator}`. The comparison in force resolves in this order:

1. the operator the state names — only if it is one the filter offers (a stale
   saved view or a crafted URL naming another one is ignored);
2. `->defaultOperator()`;
3. `->operator()` — `like` (contains) unless you set another;
4. if that is not among the offered operators, the first one offered.

**What each comparison costs.** *contains*, *starts with*, *ends with*, *is* and
the comparisons are one SQL clause and go through the canonical `QueryPlanner`,
so joins and column qualification are handled as for every other filter. Three
cannot be one clause and constrain the query themselves:

| Operator | SQL |
|---|---|
| does not contain | `column IS NULL OR column NOT LIKE '%v%'` |
| is not | `column IS NULL OR column != 'v'` |
| is empty | `column IS NULL OR column = ''` |
| is not empty | `column IS NOT NULL AND column != ''` |

The negatives **keep `NULL` rows** — "does not contain gmail" includes the
customers with no email — and on text *is empty* also matches `''`. Across a
relation (a dotted name) a negative is `whereDoesntHave` with the positive
comparison: a customer with one gmail address and one other is excluded.

**Defaults.** No operator list means no picker: the filter compares the one way
`->operator()` says, and its state is the plain value. An operator that needs no
value (*is empty*, *is not empty*) makes the filter active with the text box
empty.

**The trap.** `->operator('>=')` and the other comparison spellings are kept for
a fixed comparison, but they are not in the picker's vocabulary. Combined with
`->operators()`, a default the picker does not offer falls to the first offered
operator — use `->defaultOperator()` with one of the listed ones instead.

## Basic Usage

```php
TextFilter::make('name')
// WHERE name LIKE '%value%'
```

## A Fixed Comparison

```php
TextFilter::make('sku')
    ->operator('starts_with')   // WHERE sku LIKE 'value%'

TextFilter::make('email')
    ->operator('=')             // exact match

TextFilter::make('age')
    ->operator('>=')            // numeric / lexical comparison
```

Accepted: `like` (default), `starts_with`, `ends_with`, `equals` / `=`, `!=` /
`<>`, `>`, `>=`, `<`, `<=`, and any `FilterOperator` value (`not_contains`,
`is_empty`, …).

## Letting The User Choose

```php
use NyonCode\WireTable\Enums\FilterOperator;

TextFilter::make('email')
    ->operators()                                 // every text operator

TextFilter::make('email')
    ->operators(['contains', 'not_contains', 'ends_with', 'is_empty'])
    ->defaultOperator(FilterOperator::EndsWith)   // what a fresh filter starts on
```

The text operators: *contains*, *does not contain*, *is*, *is not*, *starts
with*, *ends with*, *is empty*, *is not empty*. An operator a text filter cannot
apply is dropped from the list. With the picker on, the chip says the comparison
(`Email: does not contain gmail`, `Phone: is empty`) and `Table::queryString()`
writes the operator under the filter's parameter with an `_op` suffix.

## Custom Query Logic

```php
TextFilter::make('title')
    ->query(fn (Builder $query, $value) => $query->where('title', 'like', "%{$value}%"))
```

A `->query()` callback replaces the comparison, picker or not.

## Extended Example

A customer list where support staff look for "every customer without a phone"
or "everyone not on a gmail address" — questions a contains-only box cannot ask.

```php
<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class ListCustomers extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Customer::class)
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('email'),
                TextColumn::make('phone'),
            ])
            ->filters([
                TextFilter::make('name')->debounce(300), // contains, no picker
                TextFilter::make('email') // [tl! focus:start]
                    ->operators(['contains', 'not_contains', 'ends_with', 'is_empty'])
                    ->defaultOperator(FilterOperator::EndsWith),
                TextFilter::make('phone')
                    ->operators([FilterOperator::IsEmpty, FilterOperator::IsNotEmpty]), // [tl! focus:end]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-customers');
    }
}
```

`?filter_email=gmail.com&filter_email_op=not_contains` reproduces "not on gmail", NULL emails
included.

## TextFilter API

```php
->operator(string $operator)         // the fixed comparison — default 'like'; also '=', '!=', '<>', '>', '>=', '<', '<=' or a FilterOperator value
->operators(array|bool $operators = true) // inherited — offer a picker: true = every text operator, or a list of FilterOperator|string
->defaultOperator(FilterOperator|string $operator) // inherited — what the picker starts on
->debounce(?int $ms)                 // debounce for the live input
->query(Closure $fn)                 // custom query: fn (Builder $query, $value): Builder
->getOperator(): string              // the fixed comparison as configured
```

## Related

- [Filters](index.md#operators) — the operator vocabulary every filter shares
- [Column-Level Filters](column-level.md) — the same filter in a column header, with `->filterOperators()`
- [QueryBuilderFilter](query-builder.md) — text conditions combined with AND / OR

---
order: 31
summary: Conditions combined with "all of" / "any of", one level of groups, each condition an ordinary filter reused — the advanced filter for people who build their own question.
---

# QueryBuilderFilter

A filter the user assembles: a list of conditions — *Customer contains Acme*,
*Status is any of Paid*, *Issued is before 1 March* — combined with **all of**
(AND) or **any of** (OR), with one level of groups for "all of these, and any of
those". Reach for it when the questions people ask a list are too varied to
predict with a fixed row of filters.

```php
use NyonCode\WireTable\Filters\QueryBuilderFilter;
```

## How It Works

**It owns no comparison.** Every field a condition can be about is an ordinary
filter you pass to `->constraints()` — a `TextFilter`, a `SelectFilter`, a
single-date `DateFilter`, a `TernaryFilter`. A condition is three things: which
of those filters (the *field*), which of its [operators](index.md#operators),
and a value. The builder only arranges them; the comparison is the filter's own
code. So *does not contain* keeps `NULL` rows, *is empty* on text also matches
`''`, a dotted name crosses a relation with `whereHas` / `whereDoesntHave`, and
the chip words a condition exactly as a header filter would — because it is the
same code doing it.

**What `->constraints()` does to the filters it is given:**

1. A `SelectFilter` is made `->multiple()`, so its condition reads *is any of*.
2. A filter with no operator list gets `->operators()` — every operator its type
   supports. A list you set yourself is kept.
3. Its state path is moved under the builder (`tableState.filters.<builder>`).

The filters are changed in place — build fresh instances for the builder rather
than passing the same object you also list in `Table::filters()`.

**The state** is a plain array, `{combinator, rules}`. A rule is
`{field, operator, value}`; a group is another `{combinator, rules}`:

```php
[
    'combinator' => 'and',
    'rules' => [
        ['field' => 'status', 'operator' => 'in', 'value' => ['paid']],
        ['combinator' => 'or', 'rules' => [
            ['field' => 'customer', 'operator' => 'contains', 'value' => 'Acme'],
            ['field' => 'customer', 'operator' => 'contains', 'value' => 'Beta'],
        ]],
    ],
]
```

Every read normalises it: a combinator other than `'or'` is `'and'`, a rule that
is not an array is dropped, and **groups exist one level deep only** — a group
inside a group is discarded. That limit is deliberate: a tree a person can read
back is one they can check. A new group starts with the *opposite* combinator to
the top level, since a group exists to say "any of" inside "all of" (or the
reverse).

**The query.** The whole tree becomes one parenthesised `where (…)`, each rule a
nested `where` joined by `and` / `or`. A rule is skipped while it compares
nothing — no value and an operator that needs one, or a field that is not a
constraint (a stale saved view, a crafted payload). A group with no live rule is
skipped with it, and a builder with no live rule is not active at all: no chip,
no query cost. Each rule's column is qualified against the base table, so a
join another filter added cannot make it ambiguous. The builder is never planned
by the `QueryPlanner` — it always runs through its own `apply()`.

**Where it runs.** Adding a condition, adding a group and removing one are
Livewire actions on the `WithTable` component — `queryBuilderAddRule()`,
`queryBuilderAddGroup()`, `queryBuilderRemove()` — and each returns the table to
page one. The field and operator selects are always live, because they decide
which value control is drawn. The values follow the panel: live, or held until
Apply when the table [defers its filters](index.md#the-filter-panel).

**Persistence.** The tree is too nested for a query string, so it is **not**
written to the URL with `Table::queryString()`; a saved view keeps it. The chip
is one sentence built from the constraints' own chips — *Customer contains Acme
and (Status is any of Paid or …)* — and `->indicator()` replaces it.

**The traps:**

- **A range has no place in a condition.** A `DateFilter` with `->range()` or
  `->presets()`, and a `NumberRangeFilter`, have two bounds and no single value;
  a condition draws one control. Keep them as ordinary panel filters beside the
  builder.
- **The builder takes a whole row** of the panel grid (`spansFullWidth()`), even
  under `Table::filtersFormColumns()`.
- **A `->query()` on the builder replaces all of the above** and receives the
  normalised tree as `$value`; a `->query()` on one constraint runs for that
  condition only, with the condition's value.

## Basic Usage

```php
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\QueryBuilderFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;

QueryBuilderFilter::make('conditions')
    ->label('Conditions')
    ->constraints([ // [tl! focus:start]
        TextFilter::make('customer'),
        SelectFilter::make('status')->options(['paid' => 'Paid', 'pending' => 'Pending']),
        DateFilter::make('issued_at')->label('Issued'),
    ]) // [tl! focus:end]
```

The panel shows *Add condition* and *Add group*. A new condition starts on the
first constraint and that filter's default operator.

## Choosing The Operators

Each constraint offers every operator its type supports unless you narrow it.
Narrow it on the constraint itself, with the same `->operators()` and
`->defaultOperator()` any filter takes:

```php
use NyonCode\WireTable\Enums\FilterOperator;

QueryBuilderFilter::make('conditions')->constraints([
    TextFilter::make('customer')
        ->operators(['contains', 'not_contains', 'is_empty']), // [tl! focus]
    TextFilter::make('number')
        ->label('Invoice')
        ->defaultOperator(FilterOperator::StartsWith), // still every text operator [tl! focus]
    SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
        ->operators([FilterOperator::In, FilterOperator::NotIn]),
])
```

| Constraint | Value control | Operators it can offer |
|---|---|---|
| `TextFilter` | text input | contains, does not contain, is, is not, starts with, ends with, is empty, is not empty |
| `SelectFilter` | the filter's options (always multiple) | is any of, is none of, is empty, is not empty |
| `DateFilter` (single date) | date input | is on, is not on, is before, is on or before, is after, is on or after, is empty, is not empty |
| `TernaryFilter` | yes / no | none — the value is the comparison |

An operator that needs no value (*is empty*) draws no value control.

## Groups

*Add group* appends a group holding one empty condition, with the combinator
opposite to the top level's. Removing a group's last condition removes the group.
There is no group inside a group.

## Fields Across A Relation

A dotted constraint name is a relation path, handled as it is everywhere else:
a positive operator is `whereHas`, a negative one is `whereDoesntHave` with the
positive comparison — "no related record matches", not "some related record does
not".

```php
QueryBuilderFilter::make('conditions')->constraints([
    TextFilter::make('customer.email')->label('Customer email'),
    SelectFilter::make('lines.product_id')
        ->label('Contains product')
        ->options(fn () => Product::pluck('name', 'id')->all()),
])
```

## Extended Example

The invoice list from the workbench preview: three quick filters for everyday
use, and a query builder for the question nobody predicted, all in a panel above
the rows that applies on *Apply*.

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FiltersLayout;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\QueryBuilderFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class ListInvoices extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number')->label('Invoice')->sortable(),
                TextColumn::make('customer')->label('Customer'),
                BadgeColumn::make('status')->label('Status'),
                TextColumn::make('issued_at')->label('Issued')->date(),
            ])
            ->filters([
                TextFilter::make('customer')->label('Customer')->operators(),
                SelectFilter::make('status')->label('Status')->multiple()
                    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
                    ->withCounts(),
                DateFilter::make('issued_at')->label('Issued')->presets(),
                QueryBuilderFilter::make('conditions') // [tl! focus:start]
                    ->label('Conditions')
                    ->constraints([
                        TextFilter::make('customer')->label('Customer'),
                        TextFilter::make('number')->label('Invoice'),
                        SelectFilter::make('status')->label('Status')
                            ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue']),
                        DateFilter::make('issued_at')->label('Issued'),
                    ]), // [tl! focus:end]
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters();
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

The builder spans the full width of the three-column grid. Its constraints are
new instances, not the panel filters above it, so the two never share state.

## QueryBuilderFilter API

```php
QueryBuilderFilter::make(string $name)
->constraints(array $filters)          // list<Filter> — the fields a condition may be about; selects become multiple, operators default to all supported
->label(?string $label)                // inherited — the label above the conditions
->indicator(string|Closure|null $indicator) // inherited — replaces the one-sentence chip; fn ($tree, Filter $filter)
->query(Closure $callback)             // inherited — replaces the whole builder; fn (Builder $query, array $tree): Builder
->getConstraints(): array              // array<string, Filter> keyed by name
->getConstraint(?string $field): ?Filter
->getFirstConstraint(): ?Filter        // where a new condition starts
->hasActiveRule(mixed $state): bool    // whether any condition would narrow the rows
```

Component actions the builder's buttons call (from `WithTable`):

```php
$component->queryBuilderAddRule(string $filter, ?int $group = null); // add a condition, at the top level or in group $group
$component->queryBuilderAddGroup(string $filter);                    // add a group with one empty condition
$component->queryBuilderRemove(string $filter, int $index, ?int $group = null); // remove a condition; an emptied group goes with it
```

## Related

- [Filters](index.md) — operators, the filter panel and the shared filter API
- [TextFilter](text.md), [SelectFilter](select.md), [DateFilter](date.md), [TernaryFilter](ternary.md) — the constraints
- [Relationship & Sub-Row Filters](relationships.md) — dotted names and relation semantics

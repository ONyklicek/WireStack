---
order: 23
summary: One cell divided horizontally between several child columns, each keeping its own rendering.
---

# SplitColumn

Horizontally splits space between multiple child columns.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

## How It Works

The split renders each child through the child's own `renderCell()` — its
formatting, prefix, text styling, icon and link — and lays the results out in one
flex container: a row by default, a column after `vertical()`. An `ImageColumn`
child is taken out of that flow and drawn first, beside the rest.

- **Every child gets a box of its own.** A child's markup is often bare text, and
  bare text dropped into a flex container is one anonymous item — two stacked
  amounts used to print as `500 Kč1 000 Kč` on one line. A child that renders
  nothing (hidden, or not visible for the record) gets no box, so it leaves no gap.
- **Alignment comes from two places.** A child never gets a `<td>` of its own, so
  its `alignRight()` goes on its box instead; a child that sets none inherits the
  split's. The split's own `alignRight()` aligns the text of a `vertical()` split
  and, in a row, moves the children to the end of the cell (`justify-end`).
  `alignCenter()` and `alignStart()` are different: on a split they align the
  children on the **cross axis**, as described under [Gap & Alignment](#with-gap-alignment).
- **Search and sort reach the children.** The split is searchable as soon as any
  child is, and the query searches each searchable child — its own
  `searchable(['first_name', 'last_name'])` columns when it names them, else its
  name. You do not mark the split itself `->searchable()`. Sorting orders by the
  first sortable child; see [How Sorting a Split Works](#how-sorting-a-split-works).
- **A child is not a column of the table.** It has no header, no toggle and no
  summary of its own, and its `->sortable()` only counts when it is the first
  sortable child.

## Basic Split

```php
SplitColumn::make('name_status')
    ->columns([
        TextColumn::make('name')->weight('bold'),
        BadgeColumn::make('status')->colors([...]),
    ])
```

## Vertical Layout

```php
SplitColumn::make('address')
    ->columns([
        TextColumn::make('street'),
        TextColumn::make('city'),
        TextColumn::make('country'),
    ])
    ->vertical()
```

## Two Values, One Under the Other

A paid amount over the total it is part of: `vertical()` stacks the children,
`prefix()` writes the joining word, and the text styling of the second child mutes
it. Both stay searchable, and the header sorts by the paid amount.

```php
SplitColumn::make('pay_status')
    ->label('Paid')
    ->vertical()
    ->gap('none')
    ->columns([
        TextColumn::make('paid_amount')->money('Kč')->weight('medium')->sortable()->searchable(),
        TextColumn::make('total_amount')->money('Kč')->prefix('z ')->textColor('muted')->textSize('sm')->searchable(), // [tl! focus]
    ])
```

```text
500 Kč
z 1 000 Kč
```

Add `->alignRight()` to the split to line the amounts up on the right edge of the
cell, the way a money column usually sits.

## With Gap & Alignment

```php
SplitColumn::make('user_info')
    ->columns([
        ImageColumn::make('avatar')->circular()->size('sm'),
        TextColumn::make('name'),
    ])
    ->gap('sm')          // 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl', or a step 0–12
    ->alignCenter()      // centre on the cross axis
```

**Alignment follows the cross axis**, which is what the layout leaves over: in the
default row that is vertical, in a `vertical()` column it is horizontal. A row
centres by default; a column stretches, and keeps stretching until you ask for
something else, so adding `vertical()` never re-lays-out a split you already had.

```php
SplitColumn::make('address')
    ->columns([TextColumn::make('street'), TextColumn::make('city')])
    ->vertical()
    ->alignStart()       // [tl! focus] leading edge, instead of full-width children
```

`gap()` takes either vocabulary — a design-system name or a step on Tailwind's
0–12 scale, as an int or a numeric string — and both resolve through the shared
`Foundation\Support\GapScale`, which is what turns them into a **literal**
utility. Anything it does not recognise falls back to the split's default rather
than reaching the page as a class Tailwind never generated.

## SplitColumn API

```php
->columns(array $columns)            // Column[] child columns
->vertical()                         // vertical layout
->horizontal()                       // horizontal layout (default)
->gap(string|int $gap)               // 'none'|'xs'|'sm'|'md'|'lg'|'xl', or a step 0–12
->getGapClass(): string              // the literal gap utility
->alignCenter(bool $align = true)    // centre on the cross axis
->alignStart()                       // start of the cross axis
->getAlignClass(): string            // '' while no alignment was asked for
->getJustifyClass(): string          // a row's 'justify-*' from alignRight()/alignLeft(), '' when unset or vertical
->getColumns(): array
```

What the split answers to the query seam on behalf of its children:

```php
->hasCapability(Capability $capability): bool  // Searchable/Sortable: true if any child has it, else its own flag
->isSearchable(): bool               // true if any child is searchable
->getSearchColumns(): array          // each searchable child's explicit search columns, else its name
->isSortable(): bool                 // true if any child is sortable, else its own flag
->getSortColumn(): ?string           // first sortable child, else its own name
```

## How Sorting a Split Works

A split is registered under a name for the group it draws — `'identity'` above —
and that name is usually not an attribute of the model. Two methods therefore
answer two different questions:

- `isSortable()` makes the header clickable as soon as **any** child is sortable.
  It falls back to the split's own `->sortable()` flag.
- `getSortColumn()` names what the click orders by: the **first sortable child**,
  falling back to the split's own name.

`TableQueryService` asks `getSortColumn()` rather than reusing the name the header
was clicked under, so the two never have to agree:

```php
SplitColumn::split([
    ImageColumn::make('avatar'),
    TextColumn::make('name')->sortable(),   // [tl! focus]
    TextColumn::make('email'),
], 'identity')
// header reads "Identity", ORDER BY runs on `name`
```

Register the split under a real attribute and mark it `->sortable()` yourself when
you want the group to order by something none of its children shows.

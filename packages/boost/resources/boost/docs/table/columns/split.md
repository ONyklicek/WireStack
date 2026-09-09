---
order: 23
summary: One cell divided horizontally between several child columns, each keeping its own rendering.
---

# SplitColumn

Horizontally splits space between multiple child columns.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

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
->getColumns(): array
```

What the split answers to the query seam on behalf of its children:

```php
->isSearchable(): bool               // true if any child is searchable
->getSearchColumns(): array          // merged from children
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

---
order: 23
nav: false
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
    ->gap('sm')          // 'xs', 'sm', 'md', 'lg'
    ->alignCenter()      // vertical center alignment
```

## SplitColumn API

```php
->columns(array $columns)            // Column[] child columns
->vertical()                         // vertical layout
->horizontal()                       // horizontal layout (default)
->gap(string $gap)                   // 'xs', 'sm', 'md', 'lg'
->alignCenter(bool $align = true)    // vertical center
->alignStart()                       // vertical top
->getColumns(): array
->isSearchable(): bool               // true if any child is searchable
->getSearchColumns(): array          // merged from children
->isSortable(): bool                 // true if first child is sortable
->getSortColumn(): ?string           // from first sortable child
```

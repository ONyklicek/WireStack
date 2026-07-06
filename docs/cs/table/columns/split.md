---
order: 23
nav: false
---

# SplitColumn

Vodorovně rozdělí prostor mezi několik dětských sloupců.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

## Základní split

```php
SplitColumn::make('name_status')
    ->columns([
        TextColumn::make('name')->weight('bold'),
        BadgeColumn::make('status')->colors([...]),
    ])
```

## Vertikální layout

```php
SplitColumn::make('address')
    ->columns([
        TextColumn::make('street'),
        TextColumn::make('city'),
        TextColumn::make('country'),
    ])
    ->vertical()
```

## S mezerou a zarovnáním

```php
SplitColumn::make('user_info')
    ->columns([
        ImageColumn::make('avatar')->circular()->size('sm'),
        TextColumn::make('name'),
    ])
    ->gap('sm')          // 'xs', 'sm', 'md', 'lg'
    ->alignCenter()      // svislé zarovnání na střed
```

## API SplitColumn

```php
->columns(array $columns)            // Column[] dětské sloupce
->vertical()                         // vertikální layout
->horizontal()                       // horizontální layout (výchozí)
->gap(string $gap)                   // 'xs', 'sm', 'md', 'lg'
->alignCenter(bool $align = true)    // svisle na střed
->alignStart()                       // svisle nahoru
->getColumns(): array
->isSearchable(): bool               // true, pokud je jakékoli dítě searchable
->getSearchColumns(): array          // sloučené z dětí
->isSortable(): bool                 // true, pokud je první dítě sortable
->getSortColumn(): ?string           // z prvního sortable dítěte
```

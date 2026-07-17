---
order: 23
nav: false
---

# StackedColumn

Vertically stacks content — avatar + primary text + secondary text. Perfect for "user" cells.

```php
use NyonCode\WireTable\Columns\StackedColumn;
```

## Avatar + Name + Email Pattern

```php
StackedColumn::make('user')
    ->avatar('avatar_url')
    ->primary('name')
    ->secondary('email')
    ->circular()
    ->avatarSize('md')
```

## Without Avatar

```php
StackedColumn::make('details')
    ->primary('title')
    ->secondary('subtitle')
```

## Avatar from Name (Generated)

```php
StackedColumn::make('user')
    ->avatarUrl(fn ($record) => null)  // no URL → generates color from name
    ->primary('name')
    ->secondary('role')
    ->circular()
```

## Custom Stack

```php
StackedColumn::make('info')
    ->stack([
        ['column' => 'name', 'weight' => 'bold'],
        ['column' => 'department.name', 'size' => 'sm', 'color' => 'gray'],
        ['column' => 'email', 'size' => 'xs', 'color' => 'gray', 'icon' => 'mail'],
    ])
```

## Search Through Stacked

```php
StackedColumn::make('user')
    ->primary('name')
    ->secondary('email')
    ->searchable()
    ->searchColumns(['name', 'email'])  // search both fields
```

## StackedColumn API

```php
->primary(string $column)            // primary (bold) text column
->secondary(string $column)          // secondary (muted) text column
->avatar(string $column)             // avatar image URL column
->avatarUrl(string|Closure $url)     // explicit avatar URL
->circular(bool $circular = true)    // round avatar
->square()                           // square avatar
->avatarSize(string $size)           // 'xs', 'sm', 'md', 'lg', 'xl'
->avatarBackground(string $color)    // fallback background color
->stack(array $items)                // custom stack items
->searchColumns(array $columns)      // columns to include in search
```

---
order: 23
nav: false
---

# StackedColumn

Skládá obsah svisle — avatar + primární text + sekundární text. Ideální pro buňky „uživatel".

```php
use NyonCode\WireTable\Columns\StackedColumn;
```

## Vzor avatar + jméno + email

```php
StackedColumn::make('user')
    ->avatar('avatar_url')
    ->primary('name')
    ->secondary('email')
    ->circular()
    ->avatarSize('md')
```

## Bez avataru

```php
StackedColumn::make('details')
    ->primary('title')
    ->secondary('subtitle')
```

## Avatar ze jména (generovaný)

```php
StackedColumn::make('user')
    ->avatarUrl(fn ($record) => null)  // žádná URL → vygeneruje barvu ze jména
    ->primary('name')
    ->secondary('role')
    ->circular()
```

## Vlastní stack

```php
StackedColumn::make('info')
    ->stack([
        ['column' => 'name', 'weight' => 'bold'],
        ['column' => 'department.name', 'size' => 'sm', 'color' => 'gray'],
        ['column' => 'email', 'size' => 'xs', 'color' => 'gray', 'icon' => 'mail'],
    ])
```

## Hledání skrz naskládané

```php
StackedColumn::make('user')
    ->primary('name')
    ->secondary('email')
    ->searchable()
    ->searchColumns(['name', 'email'])  // hledat v obou polích
```

## API StackedColumn

```php
->primary(string $column)            // sloupec primárního (tučného) textu
->secondary(string $column)          // sloupec sekundárního (tlumeného) textu
->avatar(string $column)             // sloupec URL avataru
->avatarUrl(string|Closure $url)     // explicitní URL avataru
->circular(bool $circular = true)    // kulatý avatar
->square()                           // hranatý avatar
->avatarSize(string $size)           // 'xs', 'sm', 'md', 'lg', 'xl'
->avatarBackground(string $color)    // fallback barva pozadí
->stack(array $items)                // vlastní položky stacku
->searchColumns(array $columns)      // sloupce zahrnuté do hledání
```

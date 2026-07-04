---
order: 31
nav: false
---

# SelectFilter

Dropdown filter for predefined options. The most common filter type.

```php
use NyonCode\WireTable\Filters\SelectFilter;
```

## Basic Usage

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ])
```

## With Placeholder

The first item is always an empty "All" option. To customize:

```php
SelectFilter::make('role')
    ->options([
        '' => 'All Roles',           // explicit placeholder
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
```

## Multiple Selection

```php
SelectFilter::make('tags')
    ->options(Tag::pluck('name', 'id')->toArray())
    ->multiple()
    ->label('Tags')
```

When `multiple()`, applies `whereIn()` instead of `where()`.

## Searchable Dropdown

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Renders a searchable dropdown (Alpine.js powered) instead of native `<select>`.

## Native HTML Select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // native <select> element (faster render)
```

## From Database

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Options can be a Closure — evaluated lazily on render.

## From an Enum

Pass a PHP enum class instead of an array — its cases expand to `value => label` options.
Labels come from `getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`,
otherwise the case name is headlined.

```php
SelectFilter::make('status')->options(OrderStatus::class)
```

> When a column's model attribute is **cast** to an enum, a `SelectFilter` on that column
> auto-populates its options from the enum even without calling `->options()`. This shorthand
> is for the cases where you set the filter up explicitly.

## Custom Query

```php
SelectFilter::make('has_avatar')
    ->options([
        'yes' => 'With Avatar',
        'no' => 'Without Avatar',
    ])
    ->query(fn (Builder $query, string $value) => match($value) {
        'yes' => $query->whereNotNull('avatar_url'),
        'no' => $query->whereNull('avatar_url'),
    })
```

## SelectFilter API

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] or an enum class
->multiple(bool $multiple = true)    // multi-select mode
->searchable(bool $searchable = true) // searchable dropdown
->native(bool $native = true)        // native <select>
```

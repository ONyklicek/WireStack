---
order: 31
summary: A dropdown over predefined options — the filter most tables reach for first.
---

# SelectFilter

Dropdown filter for predefined options. The most common filter type.

Renders through the same combobox as the forms `Select` field, so an open filter
looks identical to an open form select. Opt into a browser-native `<select>` with
[`->native()`](#native-html-select).

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

Adds a search input to the dropdown. The surface is the same combobox as a
non-searchable filter — only the search input is added.

## Native HTML Select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // browser-native <select> element (faster render)
```

Opts out of the shared combobox, so the filter no longer matches the form select.
Prefer it only where render cost matters more than a consistent look.

`->nativeOnMobile()` keeps the combobox from the filter's mobile breakpoint up
and renders the browser's `<select>` below it, where a phone opens its own
picker instead of a sheet:

```php
SelectFilter::make('type')
    ->options([...])
    ->nativeOnMobile()               // phone: native <select>; desktop: the combobox
```

The choice holds in the filter panel and in the column header row alike — both
render the same control, a `multiple()` filter included. `->touchOnMobile()`
opens the [touch list](../../forms/fields/select.md#touch-list-on-phones) instead,
in both places. `->native()` wins over it; the app-wide default is
`wire-core.mobile.native` (see [mobile](../../start/configuration.md#mobile)).

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
->searchable(bool $searchable = true) // add a search input to the dropdown
->native(bool $native = true)        // opt into a browser-native <select> (default: false)
->nativeOnMobile(bool $condition = true) // native <select> below the mobile breakpoint only (default: config wire-core.mobile.native)
->touchOnMobile(bool $condition = true)  // full-height touch list below the mobile breakpoint (default: config wire-core.mobile.touch)
```

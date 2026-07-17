---
order: 23
nav: false
---

# SelectColumn

Inline select dropdown — saves immediately on change.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

## Basic Usage

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

## Relationship Options

```php
SelectColumn::make('category_id')
    ->relationship('category', 'name')   // load options from a related model
```

The related list is the same for every row, so it is fetched **once per render**
and reused — not once per cell. An explicit `->options()` wins over it.

```php
// Pre-seed the list yourself from a known record (rarely needed).
SelectColumn::make('category_id')
    ->relationship('category', 'name')
    ->loadRelationshipOptions($record)
```

## Enum Options

Pass a PHP enum class to expand its cases into `value => label` options. Labels come from
`getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`, otherwise the
case name is headlined. See [Enum & JSON Casts](casts.md) for the contracts.

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Always a Native Select

An editable cell always renders a browser-native `<select>`, and offers no `->native()`
toggle. This is the one select surface that does **not** share the combobox used by
[`SelectFilter`](../filters/select.md), [`TernaryFilter`](../filters/ternary.md) and the
forms `Select`: the cell commits through `wireEditableCell` (bound with `x-model`, saved on
change) rather than through an entangled state path, which is the only binding the shared
combobox supports.

If you need a searchable dropdown, use [`SelectFilter`](../filters/select.md) for filtering,
or a forms `Select` inside an [edit action](../actions.md) for editing.

## Conditional Disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // can't change super admin
```

## SelectColumn API

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] or an enum class
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
->relationship(string $name, string $titleAttribute)  // options from a relation, loaded once per render
->loadRelationshipOptions(Model $record)             // pre-seed that list explicitly
```

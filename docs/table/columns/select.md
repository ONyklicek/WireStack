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

## Enum Options

Pass a PHP enum class to expand its cases into `value => label` options. Labels come from
`getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`, otherwise the
case name is headlined. See [Enum & JSON Casts](casts.md) for the contracts.

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Native vs Styled

```php
// Native HTML <select> (default)
SelectColumn::make('type')->options([...])->native()

// Custom styled dropdown
SelectColumn::make('type')->options([...])->native(false)
```

## Conditional Disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // can't change super admin
```

## SelectColumn API

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] or an enum class
->native(bool $native = true)       // use native <select> element
->isNative(): bool
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
->relationship(string $name, string $titleAttribute)  // options from a relation
```

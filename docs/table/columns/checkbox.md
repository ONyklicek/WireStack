---
order: 23
nav: false
---

# CheckboxColumn

An inline checkbox that writes a boolean straight to the record — the same
optimistic write path as [ToggleColumn](toggle.md), where a checkbox reads more
naturally than a switch or the table is too dense for a track.

```php
use NyonCode\WireTable\Columns\CheckboxColumn;
```

## Basic Usage

```php
CheckboxColumn::make('is_active')
```

Clicking commits immediately and rolls back with an inline error if the write is
rejected (including an optimistic-lock conflict — see
[Editing](editing.md)).

## Accent Color

```php
CheckboxColumn::make('is_active')
    ->accentColor('success')
```

## Disabling Per Record

```php
CheckboxColumn::make('is_active')
    ->disabled(fn ($record) => $record->is_locked)
```

The disabled state is enforced on the server as well, not only in the browser: a
forged request to `updateTableCell()` for a disabled row is refused.

## CheckboxColumn API

```php
->accentColor(string|Color|null $color)   // checked color, default: 'primary'
->disabled(bool|Closure $condition = true)
->getAccentColorClass(): string
```

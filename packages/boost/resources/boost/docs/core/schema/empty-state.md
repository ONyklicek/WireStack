---
order: 21
---

# Empty State

A centered icon, heading, description, and optional action buttons, shown when
there is nothing to display.

```php
use NyonCode\WireCore\Foundation\Schema\EmptyState;
```

## Usage

```php
EmptyState::make()
    ->icon('inbox')
    ->heading('No records yet')
    ->description('Create your first record to get started.')
```

## With Actions

Action buttons render below the description. Pass any `Htmlable` (for example an
action's rendered button) or a raw HTML string:

```php
EmptyState::make()
    ->icon('users')
    ->heading('No users')
    ->description('Invite someone to collaborate.')
    ->actions([
        Action::make('invite')->label('Invite user'),
    ])
```

## Methods

| Method | Description |
|--------|-------------|
| `icon(string\|Icon)` | Icon shown above the heading |
| `heading(string\|Closure)` | Primary line |
| `description(string\|Closure)` | Secondary line below the heading |
| `actions(array)` | Action buttons (`Htmlable` or HTML strings) rendered below the description |

## Standalone Tag

The same component powers the table's "no records" state and is available as a
Blade tag:

```blade
<x-wire::empty-state icon="inbox" heading="No records yet" />
```

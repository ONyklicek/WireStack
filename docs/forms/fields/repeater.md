# Repeater

`Repeater` manages repeated groups of fields and can persist `hasMany` relationship data.

## Basic Usage

```php
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;

Repeater::make('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
```

## Relationship Mode

Use `relationship()` when the repeater should save related records.

```php
Repeater::make('contacts')
    ->relationship('contacts')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
    ->addable()
    ->deletable()
    ->reorderable()
```

## Limits and UX Controls

```php
Repeater::make('contacts')
    ->minItems(1)
    ->maxItems(10)
    ->addButtonLabel('Add contact')
    ->collapsible()
```

| Method | Purpose |
|--------|---------|
| `addable()` | Allow new items |
| `deletable()` | Allow item removal |
| `reorderable()` | Allow manual reordering |
| `collapsible()` | Let users collapse item blocks |
| `collapsed()` | Start items collapsed |
| `minItems()` / `maxItems()` | Constrain collection size |
| `mutateRelationshipDataBeforeSaveUsing()` | Transform item data before persistence |

## When to Use It

Use `Repeater` when a single form owns a small to medium collection of related child records and the user should manage them inline.

If the child records need independent filtering, pagination, or heavy workflows, give them their own table or screen.

## Related Docs

- [Forms Overview](../overview.md)
- [Validation](../validation.md)

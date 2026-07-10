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
    ->itemLabel(fn (array $state) => $state['name'] ?? null)   // named items: "#1 Ada"
```

### Named items

By default each item block is headed by its number (`#1`, `#2`, …). Pass
`itemLabel()` a static string or a closure of the item's state (and index) to
show a name next to the number — handy for identifying collapsed items. The
label re-renders on the item's reactive cycle, so pair it with a `->live()`
field to update it as the user types.

```php
Repeater::make('contacts')
    ->schema([TextInput::make('name')->live()])
    ->itemLabel(fn (array $state, int $index) => $state['name'] ?? "Contact #{$index}");
```

| Method | Purpose |
|--------|---------|
| `addable()` | Allow new items |
| `deletable()` | Allow item removal |
| `reorderable()` | Allow manual reordering |
| `collapsible()` | Let users collapse item blocks |
| `collapsed()` | Start items collapsed |
| `minItems()` / `maxItems()` | Constrain collection size |
| `addable(bool)` | Allow adding new items (default `true`) |
| `deletable(bool)` | Allow removing items (default `true`) |
| `reorderable(bool)` | Allow drag-to-reorder (default `false`) |
| `collapsible(bool)` | Allow collapsing item blocks |
| `collapsed(bool)` | Start all items collapsed (implies `collapsible`) |
| `minItems(int\|null)` | Minimum item count |
| `maxItems(int\|null)` | Maximum item count |
| `addButtonLabel(string\|null)` | Label on the add button |
| `itemLabel(string\|Closure\|null)` | Name shown next to each item's number (`fn(array $state, int $index): ?string`) |
| `disabled(bool\|Closure)` | Disable add/delete/reorder controls |
| `mutateRelationshipDataBeforeSaveUsing(Closure)` | Transform item data before persistence |

## Per-Item Reactivity

Reactive behavior inside a repeater resolves **per item**: `afterStateUpdated()`, live
validation, field actions, remote select search and conditional visibility all read the item's
own state bag, and `$get`/`$set` are scoped to that item.

```php
Repeater::make('contacts')->schema([
    Select::make('type')->options(['email' => 'Email', 'other' => 'Other'])->live(),
    TextInput::make('other_detail')->visibleWhen('type', 'other'),
])
```

Here `other_detail` shows only in the rows whose own `type` is `other` — flipping row 2's
select never affects row 1. See [Reactive Fields](../reactive-fields.md) for the full accessor
reference.

## When to Use It

Use `Repeater` when a single form owns a small to medium collection of related child records and the user should manage them inline.

If the child records need independent filtering, pagination, or heavy workflows, give them their own table or screen.

## Related Docs

- [Forms Overview](../overview.md)
- [Validation](../validation.md)

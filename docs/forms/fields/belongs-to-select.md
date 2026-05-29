# BelongsToSelect

`BelongsToSelect` is a relationship-aware select field for `belongsTo` associations.

## Basic Usage

```php
use NyonCode\WireForms\Components\BelongsToSelect;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->label('Company')
    ->searchable()
```

This resolves options from the related model instead of requiring a manual `options()` array.

## Common Options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->searchable()
    ->preload()
    ->required()
```

| Method | Purpose |
|--------|---------|
| `relationship('company', 'name')` | Resolve options from the relation and title column |
| `searchable()` | Load options through search |
| `preload()` | Load options immediately instead of only on search |
| `modifyOptionsQueryUsing()` | Scope or sort the related model query |
| `createOptionForm()` | Show an inline create form for a new related record |
| `createOptionUsing()` | Customize how a new option is persisted |

## Scoped Options

```php
BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->modifyOptionsQueryUsing(fn ($query) => $query->where('active', true))
```

## Inline Create

```php
use NyonCode\WireForms\Components\TextInput;

BelongsToSelect::make('company_id')
    ->relationship('company', 'name')
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
```

## Related Docs

- [Select](select.md)
- [Forms Overview](../overview.md)

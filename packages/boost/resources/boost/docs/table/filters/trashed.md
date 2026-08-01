---
order: 31
nav: false
---

# TrashedFilter

Switches the query between live, soft-deleted, and all records.

```php
use NyonCode\WireTable\Filters\TrashedFilter;
```

Unlike every other filter this one constrains no column: it changes which global
scope applies, mapping to `withTrashed()` / `onlyTrashed()` rather than to a
where clause.

## Basic Usage

```php
Table::make()
    ->filters([
        TrashedFilter::make('trashed'),
    ])
```

Three states, of which only two are options — "without deleted" is the
placeholder, i.e. clearing the filter:

| State | Query |
|-------|-------|
| *(cleared)* | live records only — the default scope stands |
| `with` | `withTrashed()` — live and deleted together |
| `only` | `onlyTrashed()` — deleted records only |

## Labels

```php
TrashedFilter::make('trashed')
    ->label('Records')
    ->withTrashedLabel('Including archived')
    ->onlyTrashedLabel('Archived only')
```

## Restoring from the filtered view

Pair it with a row action that only shows for trashed records:

```php
Table::make()
    ->filters([TrashedFilter::make('trashed')])
    ->actions([
        Action::make('restore')
            ->visible(fn ($record) => $record->trashed())
            ->action(fn ($record) => $record->restore()),
    ])
```

## Requirements

The table's model must use `SoftDeletes`. If it does not, applying the filter
throws a `TableConfigurationException` naming the filter and the model, rather
than failing as an undefined `onlyTrashed()` deep inside the query builder.

## TrashedFilter API

```php
->withTrashedLabel(?string $label)     // default: "With deleted"
->onlyTrashedLabel(?string $label)     // default: "Only deleted"
->getWithoutTrashedLabel(): string     // the placeholder, "Without deleted"
->options(array $options)              // throws: see below

TrashedFilter::WITH                    // 'with'
TrashedFilter::ONLY                    // 'only'
```

`options()` is inherited from `SelectFilter` and does not apply: this filter
switches a soft-delete scope rather than matching a column against a value, so
an arbitrary option has nothing to do. Its own `getOptions()` overrides whatever
was set, which made the setter look accepted while changing nothing — it now
throws a `TableConfigurationException` pointing at `withTrashedLabel()` and
`onlyTrashedLabel()`, the two things that *can* be changed.

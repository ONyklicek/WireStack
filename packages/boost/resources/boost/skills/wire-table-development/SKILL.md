---
name: wire-table-development
description: Build and modify wire-table data tables — columns, filters, row/header/bulk actions, summaries, sub-rows and reordering.
---

# wire-table Development

## When to use this skill

Use when creating or changing a Livewire data table built with wire-table (a component using the
`WithTable` trait and a `table(Table $table): Table` method).

## Workflow

1. Run the `list-component-types` MCP tool with category `columns`, `filters` or `actions` to see the
   available types, then `describe-component-api` for a specific type's fluent methods.
2. Inspect an existing table with `describe-table` (pass the component class) to match conventions.
3. Build the table fluently inside `table()`.

## Patterns

```php
public function table(Table $table): Table
{
    return $table
        ->query(Invoice::query())
        ->columns([
            TextColumn::make('number')->sortable()->searchable(),
            BadgeColumn::make('status')->colorUsing(fn ($state) => $state->getColor()),
            TextColumn::make('total')->summarize(SummaryType::Sum),
        ])
        ->filters([
            SelectFilter::make('status')->options(InvoiceStatus::class)->indicator('Status'),
            DateFilter::make('issued_at')->month(),
        ])
        ->actions([EditAction::make(), DeleteAction::make()])
        ->bulkActions([DeleteBulkAction::make()])
        ->defaultSort('issued_at', 'desc');
}
```

## Rules

- A filter's query callback must return the query Builder.
- Badge/icon color & icon: use `->color('success')` for one fixed color, `->colors([state => color])` for a static map, `->colorUsing(fn ($state) => …)` for a per-row value, or nothing when the state enum implements `HasColor`. `->color()` takes `string|Color|null` — never a Closure (that is `->colorUsing()`). Icons mirror this: `->icon()` / `->icons([...])` / `->iconUsing()` / enum `HasIcon`.
- Prefer SQL-computed summaries over PHP aggregation for footer totals.
- For inline editing use `TextInputColumn`, `ToggleColumn` or `SelectColumn`.
- Whole-row interaction is **record actions**, a group separate from `->actions()`: `->recordActions([Action::make('edit')->onDoubleClick()])` (also `->onClick()`/`->onContextMenu()`/`->onKey()`). The fluent trigger returns a `RecordAction`, so it goes in `recordActions()`, not `->actions()`. Behaviour-only by default (`->alsoInRowActions()` to also show a button); keyboard nav (grid/arrows/Enter) is automatic. `Table::rowContextMenu()` is deprecated → use `->onContextMenu()`.

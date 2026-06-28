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
            BadgeColumn::make('status')->color(fn ($state) => $state->getColor()),
            TextColumn::make('total')->summarize(SummaryType::sum()),
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
- Reuse the shared `HasColor`/`HasIcon` concerns via `->color()` / `->icon()`; do not hardcode classes.
- Prefer SQL-computed summaries over PHP aggregation for footer totals.
- For inline editing use `TextInputColumn`, `ToggleColumn` or `SelectColumn`.

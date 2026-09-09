---
order: 40
summary: "A table inside a widget, and a widget that is just your own Blade view — for the panel no built-in type fits."
---

# Tables And Custom Views

Two widgets that hold something the widget system does not define: a full
[table](../../table/overview.md) with its search, filters and actions intact, and
a plain Blade view of your own with the widget chrome around it.

## TableWidget

A few rows of a table inside a dashboard card.

```php
use NyonCode\WireTable\Widgets\TableWidget;
```

> **It moved packages in 2.0, and it drew nothing before that.** The class used
> to be `WireCore\Widgets\TableWidget`, where it stored the callback and never
> called it — a card with a heading and an empty `<div>`. The cause was
> structural: widgets are in `wire-core`, the table engine is in `wire-table`,
> and table depends on core, so from core it could not be reached. The class
> lives where the engine is now.

### Basic Usage

```php
TableWidget::make()
    ->heading('Recent orders')
    ->limit(5)
    ->table(fn (Table $table) => $table
        ->model(Order::class)
        ->columns([
            TextColumn::make('reference'),
            TextColumn::make('customer.name'),
            TextColumn::make('total')->money('CZK'),
            BadgeColumn::make('status')->colors(['paid' => 'success']),
        ]))
```

### What it draws, and what it does not

Columns and rows. **No toolbar, no search, no filters, no pagination, no bulk
actions, no row actions** — those are a table's apparatus, and a dashboard card
that grows one is a table wearing a widget costume. Reach for a page with
`WithTable` when the reader has to *work* with the rows.

What it does share is the one thing that must not drift: every cell is
`Column::renderCell()`, so a badge, a money format or a relation path draws here
exactly as it does on a full table. The query is planned by the same
`TableQueryService`, so a column needing a join or an aggregate is costed the
same way too.

Because it stays plain markup inside the host component, the widget's polling,
filter, header actions and `wire:partial` region all keep working — which a
nested Livewire component would not.

### The row cap

```php
->limit(int $limit)   // default 5, clamped to at least 1
```

There is no pagination, so without a cap the card would render whatever the
query returned.

### TableWidget API

```php
->table(Closure $callback)           // fn (Table $table): Table
->limit(int $limit)                  // rows drawn — default 5
->getTableCallback(): ?Closure
->getLimit(): int
->getTable(): ?Table
->getRecords(): array
```

`emptyState()` is shared with every other widget — see the
[widget base](index.md#widget-api-reference).

---

## CustomWidget

Renders a custom Blade view as a widget.

```php
use NyonCode\WireCore\Widgets\CustomWidget;
```

### Basic Usage

```php
CustomWidget::make()
    ->heading('Quick Links')
    ->view('dashboard.quick-links')
    ->viewData(['links' => $this->getLinks()])
```

### CustomWidget API

```php
->view(string $view)                 // Blade view name
->viewData(array $data)              // data passed to view
->getCustomView(): ?string
```

### When To Write A Class Instead

`CustomWidget` is the right answer when the panel is markup and the data is
already in hand. It stops being the right answer the moment the widget has
behaviour of its own — a query to run, a filter to interpret, a value to format
— because all of that then lives in the dashboard that *declares* the widget
rather than in the widget, and cannot be reused, tested or subclassed.

A widget class of your own is two files, and the generator writes both:

```bash
php artisan make:wire-widget Revenue
```

See [Writing Your Own Widget](index.md#writing-your-own-widget).

---

## Related

- [Widgets](index.md) — the base class both extend
- [Tables](../../table/overview.md) — everything a table widget keeps
- [Blade Components](../foundation/blade-components.md) — what to build a custom view out of
- [Dashboards](dashboards.md) — placing them beside the built-in types

---
order: 29
summary: Ready-made column recipes — the cells applications keep rebuilding, written once.
---

# Patterns & Recipes

## User Table with Avatar

```php
$table->columns([
    StackedColumn::make('user')
        ->avatar('avatar_url')
        ->primary('name')
        ->secondary('email')
        ->circular()
        ->searchable()
        ->searchColumns(['name', 'email']),

    BadgeColumn::make('role')
        ->colors(['primary' => 'admin', 'success' => 'editor', 'gray' => 'viewer']),

    TextColumn::make('department.name')
        ->sortable()
        ->searchable(),

    TextColumn::make('posts.count')
        ->label('Posts')
        ->sortable()
        ->alignCenter(),

    TextColumn::make('last_login')
        ->since()
        ->sortable()
        ->textSize('sm')
        ->textColor('gray'),

    BooleanColumn::make('is_active'),
]);
```

## Financial Table

```php
$table->columns([
    TextColumn::make('number')
        ->searchable()
        ->fontFamily('mono'),

    TextColumn::make('client.name')
        ->searchable()
        ->sortable(),

    TextColumn::make('issued_at')
        ->date('d.m.Y')
        ->sortable(),

    TextColumn::make('due_at')
        ->date('d.m.Y'),

    TextColumn::make('total')
        ->money('CZK')
        ->sortable()
        ->alignRight()
        ->weight('bold')
        ->summarize('sum', 'Total'),

    BadgeColumn::make('status')
        ->colors([
            'draft' => 'gray',
            'sent' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
        ]),

    PollColumn::make('payment_status')
        ->intervalSeconds(30)
        ->badge()
        ->colors(['success' => 'received', 'warning' => 'pending', 'gray' => 'none'])
        ->pollWhile(fn ($state) => $state === 'pending'),
]);
```

## Task Board Table

```php
$table->columns([
    SelectColumn::make('status')
        ->options([
            'todo' => '📋 To Do',
            'in_progress' => '🔄 In Progress',
            'review' => '👀 Review',
            'done' => '✅ Done',
        ]),

    TextColumn::make('title')
        ->searchable()
        ->weight('semibold')
        ->description(fn ($r) => Str::limit($r->body, 60))
        ->actionUrl(fn ($r) => route('tasks.show', $r)),

    StackedColumn::make('assignee')
        ->avatar('assignee.avatar_url')
        ->primary('assignee.name')
        ->circular()
        ->avatarSize('sm'),

    BadgeColumn::make('priority')
        ->colors(['danger' => 'high', 'warning' => 'medium', 'gray' => 'low'])
        ->icons(['arrow-up' => 'high', 'minus' => 'medium', 'arrow-down' => 'low']),

    TextColumn::make('due_at')
        ->date('d.m.')
        ->textColor('gray')
        ->textSize('sm'),
]);
```

## A Column Class Of Your Own

When no fluent method on the shipped columns draws the cell you need, write a
column class and its view:

```bash
php artisan make:wire-column Price
```

writes `app/Tables/Columns/PriceColumn.php` and
`resources/views/tables/columns/price.blade.php`. The class overrides
`renderCell()`, the one method a table calls for a cell, and hands the view
plain data — the table's fast render path sees the override and renders the
cell in full rather than splicing it into the text column's skeleton:

```php
namespace App\Tables\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;

class PriceColumn extends Column
{
    protected ?string $view = 'tables.columns.price';

    public function renderCell(Model $record): string // [tl! focus:start]
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $state = $this->getState($record);

        return trim($this->renderView('tables.columns.price', [
            'column' => $this,
            'record' => $record,
            'state' => $state,
            'value' => $this->formatValue($state, $record),
        ]));
    } // [tl! focus:end]
}
```

The view name is set on `$view` rather than only passed to `renderView()`, so
`PriceColumn::make('price')->view('…')` still overrides it for one table, and
the package's own `tables.columns.*` views are never looked up in its place.
`value` is the formatted state — the empty-cell text when there is none — so
`->placeholder()`, `->limit()` and the rest keep working. An existing view is
never overwritten, `--force` replaces the class only, and
`php artisan vendor:publish --tag=wire-table::stubs` lets you change what it
writes.

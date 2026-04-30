# Wire Table

Enterprise-grade Livewire table component for Laravel. Depends on `wire-core` and `wire-forms`.

## Installation

See the [full installation guide](../../packages/table/docs/installation.md) including Tailwind CSS, Vite, and layout template setup. Quick version:

```bash
composer require nyoncode/wire-table
```

## Quick Start

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireCore\Actions\Action;

class UserTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable(),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn ($record) => route('users.edit', $record)),
            ])
            ->searchable()
            ->paginated();
    }
}
```

```blade
<div>
    {{ $this->table }}
</div>
```

## Features

- **13 Column Types** — Text, Badge, Boolean, Toggle, Image, Select, TextInput, Button, Icon, Stacked, Split, Poll
- **Inline Editing** — TextInputColumn, SelectColumn, ToggleColumn with validation
- **Actions** — Row, bulk, header actions with modals, confirmations, forms
- **Filters** — Select, date, date range, number range, ternary
- **Search** — Global search across columns and relationships
- **Sorting** — Column sorting with custom callbacks
- **Pagination** — Configurable per-page options
- **Polling** — Table-level and row-level auto-refresh
- **Sub-Rows** — Expandable child content
- **Responsive** — Stacked mobile layout

## Detailed Documentation

The package-level documentation is in [`packages/table/docs/`](../../packages/table/docs/):

| Document | Description |
|----------|-------------|
| [Installation](../../packages/table/docs/installation.md) | Setup and configuration |
| [Tables](../../packages/table/docs/tables.md) | Table configuration |
| [Columns](../../packages/table/docs/columns.md) | All 13 column types |
| [Actions](../../packages/table/docs/actions.md) | Row, bulk, header actions |
| [Filters](../../packages/table/docs/filters.md) | Filter types |
| [Forms](../../packages/table/docs/forms.md) | Form fields in action modals |
| [Sub-Rows](../../packages/table/docs/sub-rows.md) | Expandable rows |
| [Notifications](../../packages/table/docs/notifications.md) | Notification drivers |
| [Advanced](../../packages/table/docs/advanced.md) | Polling, keyboard shortcuts |

## Inline Editing Columns

Inline editing columns are standalone implementations (see [ADR 0003](../decisions/0003-inline-editing-columns.md)):

```php
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

$table->columns([
    TextInputColumn::make('name')
        ->rules(['required', 'string', 'max:255'])
        ->saveOnBlur(),

    SelectColumn::make('status')
        ->options(['active' => 'Active', 'inactive' => 'Inactive']),

    ToggleColumn::make('is_published'),
]);
```

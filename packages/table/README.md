# WireTable

Enterprise-grade Livewire table component for Laravel. Inline editing, actions, bulk actions, filters, polling, modals, notifications, and more.

## Features

- **Columns** - 13 column types including text, badge, boolean, toggle, image, select, text input, button, icon, stacked, split, poll
- **Inline Editing** - TextInputColumn, SelectColumn, ToggleColumn with validation, permissions, optimistic locking
- **Actions** - Row actions, bulk actions, header actions, action groups with keyboard shortcuts
- **Modals** - Confirmation dialogs, form modals, multi-step wizards, slide-overs
- **Filters** - Select, date, date range, number range, ternary (yes/no/all)
- **Search** - Global search across multiple columns, relationship search, custom search callbacks
- **Sorting** - Column sorting with custom sort callbacks, default sort
- **Pagination** - Configurable per-page options, lazy loading
- **Polling** - Table-level and row-level polling with configurable intervals
- **Notifications** - Pluggable notification drivers (session, Livewire events, Flasher)
- **Responsive** - Stacked mobile layout, responsive column visibility
- **Sub-rows** - Expandable row content with filtering
- **Styling** - Striped, bordered, compact, hoverable, custom CSS classes

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3.x
- Tailwind CSS 3.x
- Node.js & npm (for Vite asset compilation)

## Installation

```bash
composer require nyoncode/wire-table
```

This automatically installs `wire-core` and `wire-forms` as dependencies. Service providers are auto-discovered.

### Tailwind CSS Setup

Add the Wire packages' Blade views to your Tailwind content paths:

**Tailwind 3** (`tailwind.config.js`):

```js
export default {
    content: [
        './resources/**/*.blade.php',
        './app/**/*.php',
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    ],
    darkMode: 'class',
    plugins: [require('@tailwindcss/forms')],
}
```

**Tailwind 4** (`resources/css/app.css`):

```css
@import "tailwindcss";
@plugin "@tailwindcss/forms";
@source "../../vendor/nyoncode/wire-core/resources/views";
@source "../../vendor/nyoncode/wire-forms/resources/views";
@source "../../vendor/nyoncode/wire-table/resources/views";
```

Then rebuild:

```bash
npm install -D @tailwindcss/forms
npm run build
```

### Layout Template

Your layout needs Vite assets, Livewire, and the toast notification container:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot }}
    <x-wire-notifications::toast-container />
    @livewireScripts
</body>
</html>
```

> **Note:** Livewire 3 includes Alpine.js automatically. Do not add Alpine.js separately.

### Publish Config (optional)

```bash
php artisan vendor:publish --tag=wire-table-config
php artisan vendor:publish --tag=wire-core-config
php artisan vendor:publish --tag=wire-forms-config
```

### Publish Views (optional)

```bash
php artisan vendor:publish --tag=wire-table-views
php artisan vendor:publish --tag=wire-forms-views
php artisan vendor:publish --tag=wire-core-views
```

For the full installation guide including Vite setup and troubleshooting, see [Installation](docs/installation.md).

## Quick Start

### 1. Create a Livewire Component

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use NyonCode\WireTable\Contracts\HasTable;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireTable\Filters\SelectFilter;
use App\Models\User;

class UserTable extends Component implements HasTable
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),

                BadgeColumn::make('role')
                    ->colors([
                        'admin' => 'danger',
                        'editor' => 'warning',
                        'user' => 'success',
                    ]),

                BooleanColumn::make('is_active')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'editor' => 'Editor',
                        'user' => 'User',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn ($record) => route('users.edit', $record)),

                DeleteAction::make(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->paginated();
    }

    public function render()
    {
        return view('livewire.user-table');
    }
}
```

### 2. Create the Blade View

```blade
<div>
    {{ $this->table }}
</div>
```

## Documentation

| Section | Description |
|---------|-------------|
| [Installation](docs/installation.md) | Requirements, setup, configuration |
| [Tables](docs/tables.md) | Table configuration, queries, styling |
| [Columns](docs/columns.md) | All 13 column types and their options |
| [Actions](docs/actions.md) | Row, bulk, header actions and action groups |
| [Filters](docs/filters.md) | Select, date, number range, ternary filters |
| [Forms](docs/forms.md) | Modal form fields for action dialogs |
| [Sub-Rows](docs/sub-rows.md) | Expandable child records, flatten mode, filtering |
| [Notifications](docs/notifications.md) | Notification drivers and customization |
| [Advanced](docs/advanced.md) | Polling, lazy loading, debugging, keyboard shortcuts |

## License

MIT

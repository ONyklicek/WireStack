---
order: 20
---

# Getting Started

This guide covers the production setup for Wire in a Laravel application.

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| Laravel | 10, 11, or 12 |
| Livewire | 3.x |
| Tailwind CSS | 3.x+ |
| Alpine.js | 3.x+ (included with Livewire) |

## Installation

### Full ecosystem (table + forms + core)

```bash
composer require nyoncode/wire-table
```

### Only forms (forms + core)

```bash
composer require nyoncode/wire-forms
```

### Only core

```bash
composer require nyoncode/wire-core
```

### Sortable package (drag and drop row reordering)

```bash
composer require nyoncode/wire-sortable
```

Service providers register automatically via Laravel auto-discovery.

## Production Checklist

Before you render the first component, make sure all of these are true:

- Livewire 3 is installed
- Tailwind scans the Wire vendor views
- your app defines a `primary` color
- the main layout includes `@vite`, `@livewireStyles`, and `@livewireScripts`
- the layout renders `<x-wire-notifications::toast-container />` if you want built-in toasts

## Tailwind CSS Configuration

Wire generates some utility classes from PHP (color/size resolvers, the mobile
bottom-sheet classes, responsive grid columns, …), so Tailwind must scan both
the package **views and `src`** — scanning views alone will miss those classes.

**Tailwind 3** — add the paths to `tailwind.config.js`:

```js
module.exports = {
    content: [
        // ...your paths
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-core/src/**/*.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/src/**/*.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/src/**/*.php',
        './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-sortable/src/**/*.php',
    ],
}
```

**Tailwind 4** — add `@source` lines to your CSS entry (e.g. `app.css`):

```css
@source "../../vendor/nyoncode/wire-core/resources/views";
@source "../../vendor/nyoncode/wire-core/src";
@source "../../vendor/nyoncode/wire-forms/resources/views";
@source "../../vendor/nyoncode/wire-forms/src";
@source "../../vendor/nyoncode/wire-table/resources/views";
@source "../../vendor/nyoncode/wire-table/src";
@source "../../vendor/nyoncode/wire-sortable/resources/views";
@source "../../vendor/nyoncode/wire-sortable/src";
```

### Primary Color

Wire components use `primary` as the default accent color (buttons, badges, focus rings, etc.). You must define it in your Tailwind config:

**Tailwind 3** (`tailwind.config.js`):

```js
const colors = require('tailwindcss/colors')

module.exports = {
    theme: {
        extend: {
            colors: {
                primary: colors.blue, // or any color palette
            },
        },
    },
}
```

**Tailwind 4** (`app.css`):

```css
@theme {
    --color-primary-50: var(--color-blue-50);
    --color-primary-100: var(--color-blue-100);
    --color-primary-200: var(--color-blue-200);
    --color-primary-300: var(--color-blue-300);
    --color-primary-400: var(--color-blue-400);
    --color-primary-500: var(--color-blue-500);
    --color-primary-600: var(--color-blue-600);
    --color-primary-700: var(--color-blue-700);
    --color-primary-800: var(--color-blue-800);
    --color-primary-900: var(--color-blue-900);
    --color-primary-950: var(--color-blue-950);
}
```

> Without a `primary` color defined, buttons and other interactive elements will be invisible (white text on a transparent background).

## Layout Template

Your main layout must include Vite assets and Livewire. Add the notifications container if you use action feedback or toasts.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

Do not install Alpine separately. Livewire 3 already ships it.

## Config Publishing (optional)

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
```

## View Publishing (optional)

```bash
php artisan vendor:publish --tag=wire-core::views
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-table::views
php artisan vendor:publish --tag=wire-sortable::views
```

---

## Quick Start: Table

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

class UserTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table // [tl! focus:start]
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->searchable(),

                BadgeColumn::make('role')
                    ->colors([
                        'admin' => 'primary',
                        'editor' => 'success',
                        'viewer' => 'gray',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn (User $r) => route('users.edit', $r)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->paginated(); // [tl! focus:end]
    }
}
```

```blade
<div>
    {{ $this->table }}
</div>
```

Next: [Columns](table/columns/index.md), [Filters](table/filters/index.md), [Actions](table/actions.md)

---

## Quick Start: Form

```php
use Livewire\Component;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Toggle;

class EditUser extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(User $user): void
    {
        $this->form()->model($user)->fill($user->toArray());
    }

    public function form(Form $form): Form
    {
        return $form // [tl! focus:start]
            ->statePath('data')
            ->model(User::class)
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required(),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
                    ->required(),
                Toggle::make('active'),
            ])
            ->successMessage('User saved.'); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form()->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Save</button>
</form>
```

Next: [Field Reference](forms/fields/index.md), [Validation](forms/validation.md), [Save Lifecycle](forms/save-lifecycle.md)

## Troubleshooting

### Styles are missing

- verify the Wire vendor paths are present in Tailwind content or `@source`
- rebuild assets with `npm run build`
- clear compiled views with `php artisan view:clear`

### Components render without JavaScript behavior

- confirm the layout includes `@livewireScripts`
- remove any standalone Alpine bootstrap from `resources/js/app.js`

### Notifications do not appear

- confirm the layout renders `<x-wire-notifications::toast-container />`
- verify your configured notification driver is valid
- check whether the action actually sends a success or failure notification

---

## Development (monorepo)

```bash
git clone ...
composer install

# Run all tests
composer test

# Per-package
composer test:core    # 793 tests
composer test:forms   # 212 tests
composer test:table    # 369 tests
composer test:sortable # 10 tests

# Code style
composer lint          # Pint (Laravel preset)

# Static analysis
composer analyse       # PHPStan level 6
```

## Next Steps

- [Table columns](table/columns/index.md) — all 13 column types
- [Form fields](forms/overview.md) — all field types and Form API
- [Actions](core/actions.md) — row, bulk, header actions
- [Core plugins](core/plugins.md) — reusable app and package extensions
- [Configuration](configuration.md) — config files and environment variables
- [Authorization](authorization.md) — Gates, policies, permissions
- [Table exports](table/exports.md) — CSV, Excel, PDF downloads
- [Audit log](core/audit.md) — model change history
- [Sortable rows](sortable/overview.md) — drag & drop row reordering

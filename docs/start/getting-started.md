---
order: 20
---

# Getting Started

This guide covers the production setup for Wire in a Laravel application.

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| Laravel | 12.61+ or 13.12+ |
| Livewire | 3.x |
| Tailwind CSS | 3.x+ |
| Alpine.js | 3.x+ (included with Livewire) |
| `nyoncode/laravel-package-toolkit` | ^2.4 (installed for you) |

The last row is not something you require yourself — Composer pulls it in with the
Wire packages. It is listed because it decides the two rows above it. The toolkit
owns the `public/vendor` mirror that puts the JavaScript bundles on disk (see
[JavaScript Assets](#javascript-assets)), and 2.4 requires
`illuminate/support ^12.61.1|^13.12.0`, so that — not the `^12.0` the Wire packages
declare — is the Laravel version an install actually resolves against. An app that
pins the toolkit itself has to allow `^2.4` before `composer require nyoncode/wire-table`
can resolve at all.

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

- Livewire 4 is installed
- Tailwind scans the Wire vendor views
- your app defines a `primary` color
- the main layout includes `@vite`, `@livewireStyles`, and `@livewireScripts`
- the layout has `@wireStackScripts` in its `<head>` — required in practice for any
  app that navigates with `wire:navigate` (see [JavaScript Assets](#javascript-assets))
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

Your main layout must include Vite assets and Livewire, plus one `@wireStackScripts`
in the `<head>`. Add the notifications container if you use action feedback or toasts.

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @wireStackScripts {{-- [tl! focus] --}}
</head>
<body>
    {{ $slot }}

    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
```

Do not install Alpine separately. Livewire 4 already ships it.

## JavaScript Assets

Wire's interactive parts — dropdowns, the row context menu, tabs, wizards,
inline-edit cells, the fill handle, row selection, record actions, drag & drop
reordering — are small Alpine components delivered as pre-built bundles from
inside the packages. There is nothing to install, nothing to publish and no build
step on your side: the packages copy their bundles into `public/vendor/` themselves
and serve them as static files, cache-busted by the file's modification time.

**`@wireStackScripts` puts every installed package's bundles in the document.**
One line in the layout `<head>`, and every controller is present on every page:

```blade
<head>
    @wireStackScripts
</head>
```

Narrow it to a single package if you want to:

```blade
@wireStackScripts('wire-table')
```

### Why an SPA app wants it

Without the directive, each surface still loads its own bundle when it renders —
so a table page loads the table bundles, a form page the form ones. That works,
and an app that never adds the directive keeps working.

It stops being enough as soon as you navigate with `wire:navigate`. Livewire's
**cached Back/Forward** path does not wait for newly injected `<head>` scripts
before it initialises Alpine on the swapped-in page. A bundle that arrives *with*
the new page can therefore lose the race, and the markup is initialised against a
registry that does not have the component yet:

```text
Uncaught ReferenceError: wireRecordSelection is not defined
```

which shows up as dead dropdowns, checkboxes that do nothing and — the loudest
symptom — a full-page grey scrim over the table, because every mobile sheet
backdrop is bound to state that no longer exists.

A bundle that was already in the document when the visitor first arrived cannot
lose that race. That is the whole job of `@wireStackScripts`: it is not a
convenience, it is the only placement the cached back/forward path cannot beat.

> Heavy, optional assets — the TipTap rich editor, the Chart.js chart controller —
> are deliberately *not* in the always-loaded set. The surface that needs one fetches
> it when it renders. Charts additionally need Chart.js, which stays your app's own
> dependency.

### Where the files actually come from

They are **real files under `public/vendor/<package>`**, and they get there on
their own. The first page render after a deploy copies each package's bundles out
of the installed package and into `public/`, then emits those paths:

```html
<script src="/vendor/wire-core/wire-core-dropdown.js?id=1786118403" ...></script>
```

Nothing to run, nothing to configure. The copy is incremental — a file already
present and current is left alone — so in steady state a request does a handful of
`stat` calls and no writes at all. After an upgrade it is one copy per changed
bundle, on one request. Copies land through a temporary file and an atomic rename,
so a browser fetching a bundle mid-copy never receives a half-written one.

This matters more than it sounds. Serving the bundles from a package *route* only
works if the request reaches PHP, and a very common web-server layout answers `.js`
itself:

```nginx
location ~* \.(js|css)$ {
    try_files $uri =404;      # a route is not a file on disk → 404
}
```

On shared hosting that block is frequently not yours to change — and it breaks
Livewire's own `/livewire-{hash}/livewire.js` in exactly the same way. A file that exists
is served by every web server configuration there is, so that is what the packages
ship you.

**Publishing is still supported** and does the same copy ahead of time, which moves
it off the first request after a deploy:

```bash
php artisan vendor:publish --tag=laravel-assets --force
```

`laravel-assets` is the tag the Laravel skeleton already runs from its composer
`post-update-cmd`, so `composer update` keeps the copies current on its own. Neither
the command nor the hook is required.

### If `public/` is not writable

A read-only container, Vapor, a hardened deployment: nothing throws. The package
route serves the bundles exactly as it did before, and you want either the publish
command above (run at build time, when the filesystem still is writable) or the
`try_files … /index.php?$query_string` fall-through so the route is reachable.

If an *older* copy is already there, it keeps being served rather than falling back
to a route that may be unreachable — and the console says so, on every page and
regardless of `APP_DEBUG`, naming the bundles and the command that fixes them. See
[Troubleshooting](troubleshooting.md#javascript-404s-and-wirex-is-not-defined).

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
- confirm the layout `<head>` includes `@wireStackScripts` — especially if the
  breakage only appears after a `wire:navigate` visit or on Back/Forward
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

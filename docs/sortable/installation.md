---
title: Installation
order: 20
---

# Installation

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^10.0 / ^11.0 / ^12.0 / ^13.0 |
| Livewire | ^3.0 |
| wire-core | ^0.1 |
| wire-table | ^0.1 |
| Tailwind CSS | ^3.0 / ^4.0 |

## Install via Composer

```bash
composer require nyoncode/wire-sortable
```

The package auto-registers its service provider via Laravel package discovery.

## Install command

Run the install command to publish config and migration in one step:

```bash
php artisan wire-sortable:install
```

This will:

1. Publish the config file to `config/wire-sortable.php`
2. Publish the migration for the `reorderable_column_orders` table

## Run migrations

```bash
php artisan migrate
```

This creates the `reorderable_column_orders` table used for storing per-user column order preferences. The table has the following structure:

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `user_id` | bigint / uuid / ulid | Indexed user key. Type follows `wire-sortable.user_key_type` (`id` by default; set `uuid`/`ulid` for non-integer auth keys) |
| `model_type` | string | Fully qualified Eloquent model class name |
| `table_identifier` | string | Livewire component class name (distinguishes multiple tables over the same model) |
| `column_order` | json | Array of column names in the user's preferred order |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

A unique constraint on `(user_id, model_type, table_identifier)` ensures one column order per user, per model, per table component.

## SortableJS

Nothing to do. [SortableJS](https://sortablejs.github.io/Sortable/) is compiled into
the package's own bundle (`dist/wire-sortable.js`), served from the package's asset
route. There is no npm install, no `vendor:publish` and no CDN request, so reordering
works offline and under a strict Content Security Policy.

### JavaScript delivery

Like every wireStack package, the sortable bundle is delivered two ways and either is
enough:

- the sortable table view emits it itself when it renders, and
- `@wireStackScripts` in your layout `<head>` emits it on every page.

Add the directive if your app navigates with `wire:navigate` — see
[Getting Started → JavaScript Assets](../getting-started.md#javascript-assets) for why
the layout placement is the one that survives the cached Back/Forward path.

### `sortablejs_cdn`

`config('wire-sortable.sortablejs_cdn')` defaults to `null` and no longer affects
reordering: the drag controller closes over the bundled import and never reads
`window.Sortable`.

Set it only when your **own** code needs a global `window.Sortable`, in which case the
CDN script is loaded *in addition to* the bundle:

```php
// config/wire-sortable.php
'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
```

> **Upgrading:** earlier versions loaded that CDN script by default, so an application
> could reach for the `window.Sortable` it left behind. With the default now `null`,
> that global is gone unless you ask for it. If your app's own JavaScript uses
> `window.Sortable`, either set the key above or bundle SortableJS yourself:
>
> ```bash
> npm install sortablejs
> ```
>
> ```js
> // resources/js/app.js
> import Sortable from 'sortablejs';
> window.Sortable = Sortable;
> ```
>
> Wire's own reordering is unaffected either way.

## Manual publishing

If you prefer to publish assets individually:

```bash
# Config only
php artisan vendor:publish --tag=wire-sortable::config

# Migrations only
php artisan vendor:publish --tag=wire-sortable::migrations

# Views (for customization)
php artisan vendor:publish --tag=wire-sortable::views

# Translations
php artisan vendor:publish --tag=wire-sortable::translations
```

## Tailwind CSS

Add the package views to your `content` paths so Tailwind can scan the classes:

**Tailwind v3** (`tailwind.config.js`):

```js
module.exports = {
    content: [
        // ...
        './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
    ],
};
```

**Tailwind v4** (`resources/css/app.css`):

```css
@source '../../vendor/nyoncode/wire-sortable/resources/views';
```

## Database migration for row reordering

If you plan to use row reordering, add a sort column to your model's table:

```bash
php artisan make:migration add_sort_order_to_tasks_table
```

```php
Schema::table('tasks', function (Blueprint $table) {
    $table->unsignedInteger('sort_order')->default(0)->after('id');
});
```

The column name must match the value passed to `reorderable()` (defaults to `sort_order`).

> **Tip:** You can use any column name. Just pass it to `reorderable('position')` and make sure the migration matches.

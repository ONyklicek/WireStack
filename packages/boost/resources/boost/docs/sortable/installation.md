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

The package uses [SortableJS](https://sortablejs.github.io/Sortable/) for drag & drop. By default, it loads from a CDN. You have two options:

### Option A: CDN (default)

No action required. SortableJS is loaded automatically from jsDelivr:

```
https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js
```

### Option B: Bundle it yourself

Install SortableJS via your preferred package manager:

```bash
npm install sortablejs
# or: yarn add sortablejs
# or: pnpm add sortablejs
# or: bun add sortablejs
```

Add it to your `app.js`:

```js
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

Then disable the CDN in `config/wire-sortable.php`:

```php
'sortablejs_cdn' => null,
```

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

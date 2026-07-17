---
title: Customization
order: 50
---

# Customization

## CSS classes

All styling uses CSS classes that you can override in your stylesheet.

### Row reordering

| Class | Applied to | Purpose |
|---|---|---|
| `wire-sortable-handle` | Drag handle `<div>` | Cursor, color, hover state |
| `wire-sortable-ghost` | Row placeholder during drag | Opacity and background |
| `wire-sortable-chosen` | Selected row | Background highlight |
| `wire-sortable-drag` | Floating drag clone | Background, shadow, border radius |
| `wire-sortable-th` | Header cell for handle column | Identifies the added `<th>` |

### Column reordering

| Class | Applied to | Purpose |
|---|---|---|
| `wire-sortable-column-ghost` | Column header placeholder | Opacity and background |
| `wire-sortable-column-chosen` | Selected header | Background highlight |
| `wire-sortable-column-drag` | Floating header clone | Background, shadow, border radius |

### Default styles

The package includes these default styles:

```css
/* Row ghost */
.wire-sortable-ghost {
    opacity: 0.4;
    background-color: rgb(59 130 246 / 0.1);
}

/* Row chosen */
.wire-sortable-chosen {
    background-color: rgb(59 130 246 / 0.05);
}

/* Row drag clone */
.wire-sortable-drag {
    background-color: white;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    border-radius: 0.5rem;
}

/* Column ghost */
.wire-sortable-column-ghost {
    opacity: 0.4;
    background-color: rgb(59 130 246 / 0.1);
}

/* Column drag clone */
.wire-sortable-column-drag {
    background-color: rgb(249 250 251);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    border-radius: 0.375rem;
}
```

## Dark mode

Dark mode variants are included automatically:

```css
.dark .wire-sortable-drag {
    background-color: rgb(31 41 55);
}

.dark .wire-sortable-column-drag {
    background-color: rgb(31 41 55);
}
```

The drag handle icon uses `text-gray-400 hover:text-gray-600 dark:hover:text-gray-300` for proper contrast in both themes.

The toggle button uses conditional Tailwind classes:

- **Active (reordering):** `bg-primary-100 text-primary-600` (light) / `bg-primary-900/30 text-primary-400` (dark)
- **Inactive:** `text-gray-400 hover:text-gray-600 hover:bg-gray-100` (light) / `text-gray-500 hover:text-gray-300 hover:bg-gray-700` (dark)

## Overriding styles

Add your own rules after the package styles to override defaults:

```css
.wire-sortable-ghost {
    opacity: 0.6;
    background-color: rgb(16 185 129 / 0.1); /* green instead of blue */
}

.wire-sortable-drag {
    border: 2px solid rgb(16 185 129);
}
```

## Animation

Adjust the drag animation speed in `config/wire-sortable.php`:

```php
'animation' => 300, // slower, smoother
```

Set to `0` to disable animation.

## Toggle button

The toggle button is rendered automatically in the table toolbar. It shows:

- "Reorder" (with a grip icon) when not in reorder mode
- "Done reordering" (with a check icon) when in reorder mode

The button is hidden when the table uses `alwaysReorderable()` or when row reordering is disabled.

### Translations

The button labels are translatable. Publish the translations:

```bash
php artisan vendor:publish --tag=wire-sortable::translations
```

Edit `lang/vendor/wire-sortable/{locale}/messages.php`:

```php
return [
    'reorder' => 'Reorder',
    'done_reordering' => 'Done reordering',
];
```

Included locales: `en`, `cs`.

## Publishing views

To fully customize the HTML and JavaScript:

```bash
php artisan vendor:publish --tag=wire-sortable::views
```

Published files:

| File | Description |
|---|---|
| `tables/index.blade.php` | Alpine wrapper, includes wire-table view, toolbar widgets |
| `partials/scripts.blade.php` | Alpine `wireSortable` component, SortableJS init, CSS styles |

After publishing, edit the files in `resources/views/vendor/wire-sortable/`.

## Custom user model

If your application uses a custom user model, update `config/wire-sortable.php`:

```php
'user_model' => 'App\\Models\\Admin',
```

This is used by the `ReorderableColumnOrder` model for the `user()` relationship.

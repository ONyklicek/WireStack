---
order: 20
---

# Columns

Wire Table provides **12 column types**. They all share the same base column API
for labels, visibility, authorization, sorting, formatting, and inline editing —
documented below. Pick a type for its cell rendering; reach for the shared API on
any of them.

## Column Types

| Column | Use for |
|--------|---------|
| [TextColumn](text.md) | General-purpose text with date/money/number formatting presets |
| [BadgeColumn](badge.md) | Status pills with color and icon, incl. enum self-coloring |
| [BooleanColumn](boolean.md) | True/false as an icon (check / cross) |
| [IconColumn](icon.md) | State-based or dynamically resolved icons |
| [ImageColumn](image.md) | Avatars and thumbnails |
| [ButtonColumn](button.md) | Link or Livewire-action button in a cell |
| [ToggleColumn](toggle.md) | Inline editable on/off switch |
| [SelectColumn](select.md) | Inline editable dropdown (options, relations, enums) |
| [TextInputColumn](text-input.md) | Inline editable text/number/email input |
| [StackedColumn](stacked.md) | Avatar + name + email stacked layouts |
| [SplitColumn](split.md) | Compose several columns side by side |
| [PollColumn](poll.md) | Live-polling status/progress cells |

## Concepts

- [Relation Paths & Dot Notation](relations.md) — display related-model values, aggregates, pivots
- [Enum & JSON Casts](casts.md) — enum labels/colors/icons and array/json rendering
- [Editing & Column-Level Filters](editing.md) — inline editing and per-column filter inputs
- [Patterns & Recipes](patterns.md) — full example tables

## Shared Column API

Every column inherits these capabilities from the base `Column` class.

### Factory & Identity

```php
Column::make(string $name)           // static factory — $name is dot-notation path
->label(string|Closure $label)        // display label in <th> (auto-generated from name)
->getName(): string                   // get column name
->getLabel(): string                  // get resolved label
```

### Sorting

```php
->sortable(bool $sortable = true, ?Closure $query = null)
->isSortable(): bool

// Custom sort logic
->sortUsing(Closure $fn)
```

```php
TextColumn::make('full_name')
    ->sortable()
    ->sortUsing(function (Builder $query, string $direction) {
        $query->orderBy('last_name', $direction)
              ->orderBy('first_name', $direction);
    })
```

### Searching

```php
->searchable(bool|array $searchable = true)
->isSearchable(): bool

// Pass an array to search specific DB columns (when the column name is virtual)
->searchable(['first_name', 'last_name', 'email'])

// Custom search logic
->searchUsing(Closure $fn)

// Get resolved search columns
->getSearchColumns(): array
```

> `searchColumns(array $columns)` as a separate setter exists only on `StackedColumn`. On other columns, pass the array straight to `searchable()`.

```php
// Search across multiple DB columns
TextColumn::make('user')
    ->searchable(['first_name', 'last_name', 'email'])

// Custom search logic
TextColumn::make('full_name')
    ->searchable()
    ->searchUsing(function (Builder $query, string $search) {
        $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
    })
```

### Visibility & Toggleability

```php
->hidden(bool|Closure $hidden = true)        // hide column
->isHidden(): bool

// User-toggleable (column picker)
->toggleable(bool $toggleable = true)

// Permission-based
->permission(?string $permission)            // visible only if user has permission
->visible(Closure $callback)                 // custom visibility callback (Closure only)

// Per-record cell visibility (redact a single cell by row)
->visibleForRecord(Closure $callback)        // fn ($record) => bool
```

`->hidden()`, `->permission()`, `->visible()` and `->authorize()` decide whether
the column exists in the table at all — they are evaluated **once, without a
record** (they also drive the header, column toggle and export). To hide or
redact a **single cell per row** — e.g. show `salary` only for records the user
may see — use `->visibleForRecord(fn ($record) => …)`, which runs at cell render
with the row's record. A hidden cell renders empty; the column still occupies its
place in every other row.

```php
TextColumn::make('salary')
    ->visibleForRecord(fn ($record) => auth()->user()->can('viewSalary', $record));
```

### Responsive Breakpoints

```php
->visibleFrom(string $breakpoint)      // hidden below this breakpoint
->hiddenFrom(string $breakpoint)       // hidden from this breakpoint up
->onlyOnMobile()                       // visible only on mobile (<md)
->onlyOnDesktop()                      // visible only on desktop (≥lg)
->onlyOnTabletAndUp()                  // visible from md up
->onlyOnLargeScreens()                 // visible from xl up
```

```php
TextColumn::make('phone')
    ->visibleFrom('md')          // hidden on mobile, visible from md

TextColumn::make('notes')
    ->onlyOnLargeScreens()       // only visible on xl+
```

### Responsive Display Variants

```php
// Custom render for mobile vs desktop
->mobileDisplayUsing(Closure $fn)
->desktopDisplayUsing(Closure $fn)
->hasResponsiveDisplay(): bool
```

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} <{$record->email}>")
```

### Value Formatting

```php
->formatStateUsing(Closure $fn)        // transform value for display
->displayUsing(Closure $fn)            // alias for formatStateUsing
->default(mixed $value)                // value when state is null
->placeholder(string $text)            // text shown when value is null/empty
->limit(int $chars)                    // truncate to N characters
->prefix(string $prefix)              // prepend text
->suffix(string $suffix)              // append text
->html(bool $html = true)             // render value as raw HTML
->wrap(bool $wrap = true)             // allow text wrapping (default: nowrap)
```

```php
TextColumn::make('price')
    ->prefix('$')
    ->suffix(' USD')
    ->placeholder('N/A')

TextColumn::make('bio')
    ->limit(100)
    ->tooltip(fn ($record) => $record->bio)   // show full on hover

TextColumn::make('content')
    ->html()
    ->wrap()
    ->limit(200)
```

### Text Styling

Use `->textSize()` for the cell's **font size**. `->size()` (from the shared `HasSize` concern) sets the column's *structural* size and does **not** change the text font.

```php
->textSize(string $size)               // 'xs', 'sm', 'md', 'lg', 'xl' — text font size
->weight(string $weight)              // 'thin', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold'
->textColor(string $color)            // Tailwind color name or 'gray', 'primary', etc.
->fontFamily(string $family)          // 'sans', 'serif', 'mono' (TextColumn only)
```

```php
TextColumn::make('name')
    ->weight('bold')
    ->textSize('lg')

TextColumn::make('subtitle')
    ->textSize('sm')
    ->textColor('gray')
    ->weight('light')
```

### Width & Alignment

```php
->width(string $width)                 // CSS width: '200px', '20%', 'auto'
->alignment(string $alignment)         // 'left', 'center', 'right'
->alignLeft()                          // shortcut
->alignCenter()                        // shortcut
->alignRight()                         // shortcut
```

### Icons

```php
->icon(string|Icon|null $icon, ?string $position = 'before')   // position: 'before' | 'after'
->color(string|Color $color)           // static icon/text color (for per-row color use BadgeColumn/IconColumn colorUsing())
```

```php
TextColumn::make('email')
    ->icon('mail', 'before')
    ->color('primary')
```

### URL (Clickable Cell)

```php
->actionUrl(Closure $url, bool $openInNewTab = false)   // make the cell a link
```

```php
TextColumn::make('name')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
    ->color('primary')
```

### Copyable

```php
->copyable(bool $copyable = true)      // click-to-copy icon
->copyMessage(string $msg)             // feedback text after copy
```

### Tooltip & Description

```php
->tooltip(string|Closure $tooltip)     // hover tooltip
->description(string|Closure $desc)    // secondary text below value
```

```php
TextColumn::make('title')
    ->description(fn ($record) => Str::limit($record->body, 50))
    ->tooltip(fn ($record) => "Created: {$record->created_at->format('d.m.Y')}")
```

### Summary (Aggregate Footer)

```php
->summarize(string $aggregate, ?string $label = null)
```

Available aggregates: `'sum'`, `'avg'`, `'count'`, `'min'`, `'max'`, `'range'`

See [Advanced — Summary](../advanced.md#summary-footer-aggregates) for details.

### Extra HTML Attributes

```php
->extraAttributes(array $attrs)        // on <td>
->extraHeaderAttributes(array $attrs)  // on <th>
```

```php
TextColumn::make('notes')
    ->extraAttributes(['data-testid' => 'notes-cell'])
    ->extraHeaderAttributes(['class' => 'bg-gray-100'])
```

### Pivot Columns

```php
->pivot(bool $isPivot = true)          // marks as pivot table column
->isPivot(): bool
```

For many-to-many relationships with pivot data:
```php
TextColumn::make('roles.pivot.assigned_at')
    ->pivot()
    ->dateTime('d.m.Y')
```

### State Access

```php
->state(mixed $value)                  // override state value
->getState(Model $record): mixed       // resolve state from record
```

### Custom Rendering (Blade Partials)

Every column owns its **state/configuration** and delegates **markup** to a Blade
partial under `packages/table/resources/views/tables/columns/`. The base text
cell renders through `text.blade.php`; each custom-UI column has its own partial
(`badge`, `boolean`, `icon`, `image`, `button`, `toggle`, `poll`, `split`,
`stacked`, `select`, `text-input-*`). Columns never return inline HTML from
`renderCell()` — they call `renderView('tables.columns.<name>', [...])`.

Two ways to customize the markup:

```php
// 1. Per-column override — point any column at your own Blade view.
TextColumn::make('name')->view('columns.my-name-cell');

// 2. Project-wide override — publish the package views and edit the partial.
//    php artisan vendor:publish --tag=wire-table::views
//    then edit resources/views/vendor/wire-table/tables/columns/badge.blade.php
```

View resolution order: an explicit `->view()` wins, then the package view
(`wire-table::tables.columns.<name>`), then an app-level view of the same name.
Your partial receives exactly the data the built-in one does — the already
resolved state/config primitives for that column — so you only rewrite the HTML.

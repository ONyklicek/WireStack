---
order: 20
summary: Every column type, and the base API they all share — labels, visibility, authorization, sorting, formatting and inline editing.
---

# Columns

Wire Table provides **19 column types**. They all share the same base column API
for labels, visibility, authorization, sorting, formatting, and inline editing —
documented below. Pick a type for its cell rendering; reach for the shared API on
any of them.

## Column Types

| Column | Use for |
|--------|---------|
| [TextColumn](text.md) | General-purpose text with date/money/number formatting presets |
| [BadgeColumn](badge.md) | Status pills with color and icon, incl. enum self-coloring |
| [MoneyColumn](money.md) | Amounts, right-aligned and tabular; the stacked card's metric |
| [MetricColumn](metric.md) | A measurement: aggregate figure with an optional trend line |
| [PhoneColumn](phone.md) | A phone number, written to be read and linked to be dialled |
| [BooleanColumn](boolean.md) | True/false as an icon (check / cross) |
| [IconColumn](icon.md) | State-based or dynamically resolved icons |
| [ImageColumn](image.md) | Avatars and thumbnails |
| [ButtonColumn](button.md) | Link or Livewire-action button in a cell |
| [ToggleColumn](toggle.md) | Inline editable on/off switch |
| [CheckboxColumn](checkbox.md) | Inline editable checkbox (a denser ToggleColumn) |
| [SelectColumn](select.md) | Inline editable dropdown (options, relations, enums) |
| [TextInputColumn](text-input.md) | Inline editable text/number/email input |
| [StackedColumn](stacked.md) | Avatar + name + email stacked layouts |
| [SplitColumn](split.md) | Compose several columns side by side |
| [PollColumn](poll.md) | Live-polling status/progress cells |
| [ColorColumn](color.md) | A stored CSS color as a swatch |
| [RatingColumn](rating.md) | A numeric score as stars |
| [TagsColumn](tags.md) | A multi-value state as chips |

## Concepts

- [Relation Paths & Dot Notation](relations.md) — display related-model values, aggregates, pivots
- [Enum & JSON Casts](casts.md) — enum labels/colors/icons and array/json rendering
- [Editing & Column-Level Filters](editing.md) — inline editing and per-column filter inputs
- [Fill Handle](fill-handle.md) — Excel-style drag-to-fill across rows, in one request
- [Patterns & Recipes](patterns.md) — full example tables

## Shared Column API

Every column inherits these capabilities from the base `Column` class.

### Factory & Identity

```php
Column::make(string $name): static     // static factory — $name is dot-notation path
->label(string|Closure|null $label): static // display label in <th> (auto-generated from name)
->getName(): string                   // get column name
->getLabel(): string                  // get resolved label
```

### Sorting

```php
->sortable(bool $sortable = true, ?Closure $query = null): static
->isSortable(): bool
->getSortColumn(): ?string           // the attribute the header orders by

// Custom sort logic
->sortUsing(Closure $fn): static
```

`isSortable()` decides whether the header is clickable; `getSortColumn()` decides
what the click orders by. For an ordinary column the two are the same string — its
own name, dotted relation path included — so you never call it. A **composite**
column is where they part: a `SplitColumn` is registered under a name for the
group it draws, and answers with the first sortable child it holds. The query
seam asks the column rather than reusing the clicked name, which is what keeps a
composite header from ordering by an attribute that does not exist.

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
// [tl! focus]
->searchable(bool|array $searchable = true): static
->isSearchable(): bool

// Pass an array to search specific DB columns (when the column name is virtual)
->searchable(['first_name', 'last_name', 'email'])

// Custom search logic
->searchUsing(Closure $fn): static

// Declare what the column holds, so >100 and 10..20 can be typed into search
->searchAs(SearchValueType|string $type): static // 'text' | 'numeric' | 'date' | 'code'

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

The callback is called once per word of the term — `jan novak` calls it with
`jan` and then with `novak`, and both must find the row — because the search
splits on spaces by default. Under `->search(fn ($s) => $s->literal())` it
receives the whole term once.

`searchAs()` matters only once the table opts into
[range search](../overview.md#search-syntax). The value type is normally
inferred from the model's casts — a `decimal:2` or `datetime` cast is enough —
so declare it only where the casts cannot speak for the column:

```php
// The model has no cast for `amount`, so nothing can be inferred from it.
TextColumn::make('amount')
    ->searchable()
    ->searchAs('numeric')      // now ">1000" and "10..20" reach this column
```

A column left as text is skipped by a comparison rather than compared
lexically, so a wrong or missing declaration narrows what search understands —
it never returns wrong rows.

The declaration alone switches nothing on. A searchable column that declares a
type while the table's search does not read ranges is refused when the table
renders, naming the call it is missing — the alternative is a table that comes
back empty because `10..20` was looked for as literal text.

`'code'` is the one type that is *never* inferred: it says the value is a series
plus a **zero-padded** number (`8866 01`, `8866 02`), which is what makes
comparing it as text correct, and only the owner knows that. It unlocks
[ranges inside a series](../overview.md#ranges-inside-a-structured-code) —
`8866 01..08`.

### Visibility & Toggleability

```php
->hidden(bool|Closure $hidden = true): static // hide column
->isHidden(): bool

// User-toggleable (column picker)
->toggleable(bool $toggleable = true): static

// Permission-based
->permission(?string $permission): static    // visible only if user has permission
->authorize(?string $ability): static        // authorize through a Laravel Gate ability
// [tl! focus]
->authorizeUsing(?Closure $callback): static  // fn ($user, $record = null) => bool
->visible(bool|Closure $condition = true): static // custom visibility condition

// Per-record cell visibility (redact a single cell by row)
->visibleForRecord(Closure $callback): static // fn ($record) => bool
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
->visibleFrom(string|Breakpoint $breakpoint): static // hidden below this breakpoint
->hiddenFrom(string|Breakpoint $breakpoint): static  // hidden from this breakpoint up
->onlyOnMobile(): static                 // visible only below md
->mobileOnly(): static                   // alias for onlyOnMobile()
->onlyOnDesktop(): static                // visible from md up
->desktopOnly(): static                  // alias for onlyOnDesktop()
->onlyOnTabletAndUp(): static            // visible from sm up
->onlyOnLargeScreens(): static           // visible from lg up
->hasResponsiveVisibility(): bool
```

The `Breakpoint` enum accepts `sm`, `md`, `lg`, `xl` and `2xl`; string tokens
and enum cases are interchangeable. These helpers resolve to responsive classes
on the cell and header, rather than changing the query or removing the column.

```php
TextColumn::make('phone')
    ->visibleFrom('md')          // hidden on mobile, visible from md

TextColumn::make('notes')
    ->onlyOnLargeScreens()       // only visible on lg+
```

### Responsive Display Variants

```php
// Custom render for mobile vs desktop
// [tl! focus]
->mobileDisplayUsing(Closure $callback): static
->desktopDisplayUsing(Closure $callback): static
->mobileBreakpoint(string|Breakpoint $breakpoint): static // switches at 'md' by default
->hasResponsiveDisplay(): bool

// Where the column lands on a stacked mobile card (see Advanced → Responsive Layout)
->mobileSlot(MobileSlot|string $slot): static // 'title' | 'subtitle' | 'metric' | 'meta' | 'detail'
->mobileTitle(): static
->mobileSubtitle(): static
->mobileMetric(): static
->mobileMeta(): static
->mobileDetail(): static
->getMobileSlot(): ?MobileSlot
```

Without an explicit `mobileSlot()`, the stacked card derives a slot from column
order and alignment. Slot selection controls the card's hierarchy; it does not
hide a column at any breakpoint. `mobileDisplayUsing()` and
`desktopDisplayUsing()` instead replace the displayed content on each side of
`mobileBreakpoint()`.

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} <{$record->email}>")
```

### Value Formatting

```php
->formatStateUsing(Closure $fn): static // transform value for display
->displayUsing(Closure $fn): static     // alias for formatStateUsing
->default(mixed $default): static       // fallback when the resolved state is null or empty
->getDefault(): mixed
->placeholder(string|Closure|null $placeholder): static // text shown when value is null/empty
->getPlaceholder(): ?string
->limit(?int $chars): static            // truncate to N characters
->prefix(?string $prefix): static       // prepend text
->suffix(?string $suffix): static       // append text
->html(bool $html = true): static       // render value as raw HTML
->wrap(bool $wrap = true): static       // allow text wrapping (default: nowrap)
```

```php
TextColumn::make('price')   // [tl! focus:3]
    ->prefix("$")
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
->size(string|Size|Closure $size): static // structural size — 'xs'|'sm'|'md'|'lg'|'xl'; default 'md'
->xs(): static
->sm(): static
->md(): static
->lg(): static
->xl(): static
->getSize(): string
->textSize(string $size): static          // text font size; accepts Tailwind text-size tokens
->getTextSize(): ?string
->weight(string|FontWeight $weight): static
->getTextWeight(): ?string
->textColor(string|Color $color): static
->getTextColor(): ?string
->fontFamily(?string $family): static     // 'sans', 'serif', 'mono' (TextColumn only; null clears)
->getFontFamily(): ?string
```

`size()` controls the column's structural sizing and defaults to `md`;
`Size` enum cases, strings and closures are accepted. It is independent of
`textSize()`, which controls the cell's typography. `weight()` accepts the
canonical `FontWeight` enum or a string token.

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
->width(string $width): static          // preferred CSS width
->minWidth(string $width): static       // minimum CSS width
->maxWidth(string $width): static       // maximum CSS width
->getWidth(): ?string
->getMinWidth(): ?string
->getMaxWidth(): ?string
->alignment(string|Alignment $alignment): static // 'left' | 'center' | 'right'; default 'left'
->alignLeft(): static
->alignCenter(): static
->alignRight(): static
->getAlignment(): string
```

The three width values are optional and independent. `width()` sets the preferred
width; `minWidth()` and `maxWidth()` bound it when the browser lays out the table.
Values are passed through as CSS, not converted to Tailwind classes or normalized,
so use valid CSS values such as lengths, percentages, `auto`, or `min-content`.
The declarations are emitted on that column's `<th>`; when all three are unset,
the header gets no inline width style and the browser uses its normal table layout.

```php
TextColumn::make('reference')
    ->width('9rem')
    ->minWidth('8rem')
    ->maxWidth('12rem');
```

### Icons

```php
->icon(string|Icon|Closure|null $icon, ?string $position = 'before'): static // position: 'before' | 'after'
->color(string|Color|null $color): static // the column's colour: text, and the icon when it has no colour of its own
->iconColor(string|Color|Closure|null $color): static // the icon's colour, per record
->iconTile(bool $tile = true): static   // seat the icon in a tinted tile
```

On a **list** (`layout('list')`) reach for `iconTile()` as well: a bare tinted
glyph is enough on a grid of columns, where the row is already a line of aligned
values, but where the record is a sentence the tile is what gives the rows a left
edge for the eye to run down. Ground and ink come from the one role, so they
cannot drift apart, and only the semantic roles get a tile — a raw hue makes no
statement about *kind*, so it lands on the neutral one.

`color()` is resolved once for the whole column, which is right for a text tint
and wrong for a **status** icon whose whole job is to differ per row. That is
what `iconColor()` is for: pass a role from the shared vocabulary, or a closure
over the record. A closure gives up the column's static icon memo — the same
cost a closure `icon()` already pays, and the reason neither is the default.

```php
TextColumn::make('state')
    ->icon(fn ($record) => $record->failed ? 'x-circle' : 'check-circle')      // [tl! focus:start]
    ->iconColor(fn ($record) => $record->failed ? 'danger' : 'success')        // [tl! focus:end]

TextColumn::make('email')
    ->icon('mail', 'before')
    ->color('primary')
```

### URL (Clickable Cell)

```php
->actionUrl(Closure $callback, bool $openInNewTab = false): static // make the cell a link
->navigate(?bool $condition = true): static // true / false overrule wire-core.navigate; null follows it
```

A link to a page of this application is followed with `wire:navigate` when
`wire-core.navigate` is on; another site or a new tab is a plain link. See
[Configuration → Navigation](../../start/configuration.md#navigation).

```php
TextColumn::make('name')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
    ->color('primary')
```

### Copyable

```php
->copyable(bool $copyable = true, ?string $copyMessage = null): static // click-to-copy icon
->copyMessage(string $copyMessage): static // feedback text after copy
->getCopyMessage(): ?string
```

### Tooltip & Description

```php
->tooltip(string|Closure|null $tooltip): static // hover tooltip; null clears
->getTooltip(): ?string
->description(string|Closure $description, string $position = 'below'): static // secondary text and position
->getDescription(): string|Closure|null
```

```php
TextColumn::make('title')
    ->description(fn ($record) => Str::limit($record->body, 50))
    ->tooltip(fn ($record) => "Created: {$record->created_at->format('d.m.Y')}")
```

### Summary (Aggregate Footer)

```php
->summarize(string|Closure|SummaryType $type, ?string $label = null, string $scope = 'query', ?Closure $format = null, ?Closure $when = null): static
->summaryDecimals(int $decimals, string $decimalSeparator = ',', string $thousandsSeparator = ' '): static
```

Summary shortcuts, scopes and the full aggregate vocabulary are listed in
[Summaries](../summaries.md).

See [Advanced — Summary](../advanced.md#summary-footer-aggregates) for details.

### Extra HTML Attributes

```php
->extraAttributes(array|string $attributes): static // on <td>; arrays are escaped, strings are raw
->extraHeaderAttributes(array $attributes): static  // on <th>
```

Prefer the array form for `extraAttributes()`; string attributes are trusted
markup and must be escaped by the caller.

```php
TextColumn::make('notes')
    ->extraAttributes(['data-testid' => 'notes-cell'])
    ->extraHeaderAttributes(['class' => 'bg-gray-100'])
```

### Pivot Columns

```php
->pivot(bool $isPivot = true): static  // marks as pivot table column
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
->state(Closure $callback): static     // fn (Model $record) => mixed
->getState(Model $record): mixed       // resolve state from record
->getRawState(Model $record): mixed    // underlying value before display formatting
```

### Eager Loading For Closure-backed Values

The query planner can infer relations from a column path such as
`company.name`. It cannot inspect a closure used by `displayUsing()`, `actionUrl()`
or a color callback, so declare those relations explicitly to avoid one lazy
load per row:

```php
->loadRelations(string|array $relations): static
->getEagerLoadRelations(): array
```

Repeated calls merge and deduplicate relation names. See
[Relation Paths](relations.md#eager-loading-for-closure-backed-values).

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

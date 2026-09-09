---
order: 20
summary: "Every entry type an infolist can hold, what state each one expects, and what it renders that state as."
---

# Entries

An entry is one fact about the record. Which class you reach for is decided by
what the value *is* — a string, a state with a colour, a boolean, a list, an
image, a diff — and every one of them shares the label, icon, colour, size and
visibility vocabulary from [Foundation](../foundation/index.md).

## TextEntry

The default entry. It shares the canonical `FormatsState` concern with table columns, so `money()`, `numeric()`, `date()`, `dateTime()`, and `since()` format a value exactly as the matching `TextColumn` would.

```php
TextEntry::make('total')->money('Kč');                 // 1 234 Kč
TextEntry::make('weight')->numeric(2);                  // 1 234,50
TextEntry::make('created_at')->date();                  // 20.06.2026
TextEntry::make('updated_at')->dateTime()->since();     // 3 hours ago

TextEntry::make('status')->badge()->color(Color::Success);
TextEntry::make('email')->icon('envelope')->copyable();
TextEntry::make('bio')->limit(120);
TextEntry::make('name')->weight('bold');                // normal|medium|semibold|bold|light
TextEntry::make('notes')->prose();                      // long-form, prose styling
TextEntry::make('tags')->bulleted();                    // array → bulleted list
TextEntry::make('aliases')->listWithLineBreaks();       // array → line-separated list
```

### TextEntry API

| Method | Description |
|--------|-------------|
| `money(?string $currency = 'CZK')` | Format as currency |
| `numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')` | Format as a number |
| `date(?string $format = 'd.m.Y')` / `dateTime(?string $format = 'd.m.Y H:i')` | Format a date / datetime |
| `since()` | Render the date as a human diff (`diffForHumans`) |
| `badge(bool = true)` | Render the value as a colored pill |
| `color(string\|Color\|Closure)` | Badge / text color |
| `icon(string\|Closure, ?string $position)` | Leading icon |
| `copyable(bool = true)` | Add a copy-to-clipboard affordance |
| `limit(?int)` | Truncate long text |
| `weight(?string)` | Font weight |
| `prose(bool = true)` | Prose styling for long-form text |
| `listWithLineBreaks(bool = true)` / `bulleted(bool = true)` | Render an array state as a list |
| `formatStateUsing(Closure)` | Transform the resolved value (`$state, $record`) |

## BadgeEntry

A first-class `TextEntry` preset to render as a badge — the ergonomic form of `TextEntry::make(...)->badge()`. It inherits the full `TextEntry` API (color, icon, formatting), so the badge chrome stays owned in one place.

```php
use NyonCode\WireCore\Infolists\Components\BadgeEntry;

BadgeEntry::make('status')
    ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray)
    ->icon('check-circle');
```

## IconEntry

Renders an icon derived from the state. Use `boolean()` for true/false, or `icons()` for a value → icon map.

```php
IconEntry::make('is_verified')->boolean();              // ✓ success / ✕ danger

IconEntry::make('is_active')
    ->boolean()
    ->trueIcon('check-badge')->trueColor('success')
    ->falseIcon('no-symbol')->falseColor('gray');

IconEntry::make('status')
    ->icons(['draft' => 'pencil', 'published' => 'check', 'archived' => 'archive-box'])
    ->colors(['draft' => 'gray', 'published' => 'success', 'archived' => 'warning']);
```

### IconEntry API

| Method | Description |
|--------|-------------|
| `boolean(bool = true)` | Map truthy/falsy state to check/x icons |
| `trueIcon()` / `falseIcon()` | Override the boolean icons |
| `trueColor()` / `falseColor()` | Override the boolean colors |
| `icons(array\|Closure)` | Map state values to icon names |
| `colors(array\|Closure)` | Map state values to color names |

## BooleanEntry

A first-class `IconEntry` preset to boolean mode — the ergonomic form of `IconEntry::make(...)->boolean()`. A truthy state renders the success check icon, a falsy state the danger x icon; the icons and colors stay overridable.

```php
use NyonCode\WireCore\Infolists\Components\BooleanEntry;

BooleanEntry::make('is_verified');

BooleanEntry::make('is_active')
    ->trueIcon('check-badge')->trueColor('success')
    ->falseIcon('no-symbol')->falseColor('gray');
```

## ListEntry

Renders a collection state as a bulleted list or a row of badge chips — the middle ground between a single `TextEntry` and a full `RepeatableEntry`. The state may be an array/iterable, or a delimited string split with `separator()`. Items reuse the `TextEntry` formatting (number/money/date, `formatStateUsing()`, `limit()`).

```php
use NyonCode\WireCore\Infolists\Components\ListEntry;

ListEntry::make('tags');                                  // bulleted list

ListEntry::make('tags')->badge()->color('primary');       // badge chips

ListEntry::make('roles')->separator(',');                 // "admin, editor" → two items

ListEntry::make('categories')->badge()->limitList(3);     // first 3 chips + a "+N" pill
```

### ListEntry API

| Method | Description |
|--------|-------------|
| `badge(bool = true)` | Render items as badge chips instead of a bulleted list |
| `bulleted(bool = true)` | Toggle the list bullets (non-badge mode) |
| `separator(?string)` | Split a scalar string state into items |
| `limitList(?int)` | Cap the visible items; the rest collapse into a `+N` indicator |
| `color(string\|Color\|Closure)` | Chip / text color |
| `icon(string\|Closure)` | Leading icon on each chip |

## ImageEntry

Renders the state as one or more images. Absolute/data URLs are used verbatim; relative paths resolve through the configured `disk()`.

```php
ImageEntry::make('avatar')->circular()->imageSize(56);

ImageEntry::make('logo')->disk('public')->defaultImageUrl('/img/placeholder.png');

ImageEntry::make('gallery')->stacked()->imageSize(40);  // array state → overlapped gallery
```

### ImageEntry API

| Method | Description |
|--------|-------------|
| `disk(?string)` | Storage disk for relative paths |
| `imageSize(int)` | Width/height in pixels |
| `circular(bool = true)` | Round the image |
| `stacked(bool = true)` | Overlap multiple images |
| `defaultImageUrl(?string)` | Fallback when the state is empty |

## ColorEntry

Renders a swatch plus the color value, optionally copyable.

```php
ColorEntry::make('brand_color')->copyable();
```

## KeyValueEntry

Renders an array (or JSON-cast attribute) as a key/value table.

```php
KeyValueEntry::make('meta')
    ->keyLabel('Attribute')
    ->valueLabel('Value');
```

## HtmlEntry

Stored editor content printed as **markup** rather than as escaped text, with
every mention in it resolved on render.

```php
use NyonCode\WireCore\Infolists\Components\HtmlEntry;

HtmlEntry::make('body')
    ->zone('business');     // where mentions link to, when a resource routes per zone
```

It is a separate entry rather than a flag on [TextEntry](#textentry) on purpose:
a text entry escapes and must keep escaping. Printing markup is a decision an
application makes deliberately, about a column it controls — so it is a different
name, not a switch that quietly turns escaping off.

**Mentions are read back, not replayed.** A `@person` or `#article` stored in the
document carries an identity, not a name and a link, so the entry resolves each
one on every render: a renamed record reads renamed here, and a mention whose
record is gone (or that the viewer may not see) degrades to plain text instead of
a dead link. `zone()` is what a page that read `Zone::current()` in `mount()`
passes on, so those links land in the mount point the reader is already in;
without it they link through the default zone. See
[TiptapEditor → Mentions](../../forms/fields/tiptap-editor.md) for the writing
half.

```php
->zone(?string $zone)             // the mount point mention links resolve in
->getRenderedHtml(): string       // the document with every mention resolved
```

## RepeatableEntry

Renders a nested entry schema once per item of an iterable state — a `hasMany` relation or an array of rows.

```php
RepeatableEntry::make('items')
    ->columns(3)
    ->schema([
        TextEntry::make('label')->weight('medium'),
        TextEntry::make('price')->money('Kč'),
        TextEntry::make('qty')->numeric(),
    ]);
```

| Method | Description |
|--------|-------------|
| `schema(array)` | Entry schema rendered per item |
| `columns(int)` | Grid columns per row |
| `contained(bool = true)` | Wrap each row in a bordered card |
| `actions(array)` | Per-row action buttons (see [Actions](actions.md#actions)) |
| `with(array\|string)` | Eager-load relations on the rows (see below) |

### Avoiding N+1 on relation rows

When the rows are Eloquent models whose child entries read a **nested** relation path (e.g. `product.name` on each order line), reading that path lazily loads the relation once per row — an N+1. Declare the relations with `with()` and they are eager-loaded across every row in a single query before rendering:

```php
RepeatableEntry::make('lines')
    ->with(['product', 'tax'])              // one query per relation, not per row
    ->schema([
        TextEntry::make('product.name'),
        TextEntry::make('tax.rate')->numeric(2),
    ]);
```

`with()` is a no-op for array rows and merges across repeated calls. (The relation that backs the repeatable itself — `lines` — should be eager-loaded on the parent query as usual.)

## ChangesEntry

Renders a before-and-after diff as **one** table — a row per field that moved, the old side and the new side beside each other and coloured, with one set of headings for the whole diff.

```php
ChangesEntry::make('changes');
```

Reach for it wherever something records what changed: an audit entry, a revision, the report of a sync. It is what the audit module's entry page draws, and what the audit trail slide-over draws inside a record — the same table, so a diff reads the same in both places.

### What it accepts as state

Two shapes, and it tells them apart itself, because a caller has one of them and both mean the same thing:

- the `{old, new}` map an audit entry produces — `['status' => ['old' => 'draft', 'new' => 'sent']]`
- rows that are already `{field, before, after}`

Either way the values go through `NyonCode\WireCore\Foundation\ValueObjects\ChangeSet`, which owns **how a stored value reads**: `null` stays null so the renderer can say *(empty)*, a boolean is written `true` / `false` rather than `1` and nothing, and an array is JSON with its slashes and accents left alone.

That single owner is the point. The rule used to be written twice — once in the audit module and once in a Blade ternary in the trail — and the copy nobody could reach from a test printed booleans as `1`.

### In context

```php
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ChangesEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

public function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Section::make('what-happened')->label('What happened')->columns(2)->schema([
            TextEntry::make('event')->badge(),
            TextEntry::make('created_at'),
        ]),

        Section::make('changes')->label('Changes')->schema([
            ChangesEntry::make('changes')                                  // [tl! focus:start]
                // The state is a diff, never a record: hand it the pairs,
                // or rows you already built.
                ->state(fn ($record): array => $record->getChangeDiff())
                ->hiddenLabel()
                ->placeholder('No field changed.'),                        // [tl! focus:end]
        ]),
    ]);
}
```

`dense()` draws it a size smaller, for a diff that is supporting evidence inside something else rather than the subject of the page — which is how the audit trail slide-over draws it.

### ChangesEntry API

```php
->dense(bool $dense = true)           // smaller type, for a diff inside a slide-over or timeline
->isDense(): bool
->getRows(): array                    // [['field' => string, 'before' => ?string, 'after' => ?string], ...]
```

Everything else — `label()`, `hiddenLabel()`, `state()`, `placeholder()`, `columnSpan()`, `visible()` — is the shared entry surface documented above.

## Related

- [Infolists](index.md) — the object these are composed into
- [Actions](actions.md) — putting a button beside them
- [Columns](../../table/columns/index.md) — the table counterparts, sharing the same surfaces
- [Editable Panels](../record-panels.md) — the entries that write back

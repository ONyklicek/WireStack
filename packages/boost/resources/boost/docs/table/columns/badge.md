---
order: 23
nav: false
summary: Renders a cell as a colored pill whose color and icon are derived from the record's state.
---

# BadgeColumn

Renders the cell as a colored pill. Reach for it when the value is a *state* — a
status, a role, a priority — and the colour is what the reader actually scans
for. For a plain value that just needs an accent colour, `TextColumn` with
`->color()` is the lighter tool.

```php
use NyonCode\WireTable\Columns\BadgeColumn;
```

## How It Works

The badge asks two questions per cell — *what colour* and *what icon* — and
answers each by walking a ladder until a rung responds. **Colour**, in order:

1. **`->colorUsing()`** — the closure runs first and wins outright when it
   returns anything but `null`. Returning `null` for some states hands those
   back to the rungs below.
2. **`->colors()`** — the map, looked up by the state as key. An enum state is
   unwrapped to its backing scalar first, so `Status::Active` finds the
   `'active'` key.
3. **The state's own colour** — an enum implementing the `HasColor` contract
   names its own colour, and no map is needed at all.
4. **`->color()`** — the column's static colour, so setting one is not silently
   ignored on a stateful column.
5. **`gray`** — the neutral floor. A badge always renders with *some* colour.

**Icons** walk the same ladder — `->iconUsing()`, `->icons()`, the enum's
`HasIcon` contract, then the column's `->icon()` — with one difference: there is
no floor. Nothing matching means no icon, and the badge renders as a plain pill.

Four more things worth knowing before writing the chain:

- **The label is resolved separately from the colour.** The pill's text comes
  from the normal column formatting pipeline (`->formatStateUsing()`, casts,
  enum labels), never from the colour map. An enum state without a `HasLabel`
  contract reads as a headline of its case name — `InReview` → "In Review".
- **Pass an array to `->colors()`, not a closure.** The signature accepts
  `array|Closure` because it is shared with record-aware surfaces (infolist
  entries evaluate the closure against their record). A column configures itself
  before any record exists, so a closure map here matches nothing and every
  badge drops to the floor colour. Use `->colorUsing()` for dynamic colours.
- **Cost is per distinct state, not per row.** States are low-cardinality by
  nature, so the rendered markup is memoised by its resolved data: a thousand
  rows sharing four statuses render four badges. The closures above therefore
  run per state value, not per record — do not put record-specific logic in
  them.
- **The value is escaped.** Like every text cell, the label is escaped unless
  the column opts into `->html()` — a record value such as `<img onerror=…>` is
  text, not markup. A `null` or empty state renders the column's empty-cell
  text, not an empty pill.

## Basic Usage

The map is keyed by the **state**, and each value is the colour to wear for it:

```php
BadgeColumn::make('status')
    ->colors([
        'active' => 'success',      // green badge for 'active'
        'banned' => 'danger',       // red badge for 'banned'
        'pending' => 'warning',     // yellow badge for 'pending'
        'draft' => 'gray',          // gray badge for 'draft'
        'featured' => 'primary',    // blue badge for 'featured'
        'processing' => 'info',     // cyan badge for 'processing'
    ])
```

A state the map does not mention falls through the ladder above. Values may also
be given as the `Color` enum (`'active' => Color::Success`), and the whole
Tailwind palette is available — see [Theming](../../theming.md).

## With Icons

`->icons()` is keyed by the state the same way, and falls back to the column's
own `->icon()` when the state maps to nothing — an unset `->icon()` means no
icon. An enum state implementing the `HasIcon` contract picks its own icon with
no map at all.

```php
BadgeColumn::make('priority')
    ->colors([
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'gray',
    ])
    ->icons([
        'critical' => 'exclamation',
        'high' => 'arrow-up',
        'medium' => 'minus',
        'low' => 'arrow-down',
    ])
```

## Dynamic Colors

When the colour is a function of the value rather than a fixed vocabulary — a
score, an amount, an age — derive it. The closure receives the state and runs
before the map:

```php
// Closure-based color resolution
BadgeColumn::make('score')
    ->colorUsing(fn (int $state) => match(true) {
        $state >= 90 => 'success',
        $state >= 70 => 'info',
        $state >= 50 => 'warning',
        default => 'danger',
    })
    ->iconUsing(fn (int $state) => $state >= 90 ? 'star' : null)
```

## Enum States

An enum that implements the `HasLabel` / `HasColor` / `HasIcon` contracts
carries its own presentation, and the column needs no maps at all. The same enum
then reads identically on a table cell, an infolist entry and a `<select>`
option:

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function getLabel(): string // [tl! focus:start]
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Shipped => 'On its way',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Shipped => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return $this === self::Cancelled ? 'x-circle' : null;
    } // [tl! focus:end]
}

// With the attribute cast to the enum, the column is just the attribute.
BadgeColumn::make('status')
```

A map still beats the enum's own colour, which is how one table can present a
shared enum differently without touching the enum.

## Custom Label + Badge

`->formatStateUsing()` rewrites the pill's text without touching the colour
ladder — the map stays keyed by the raw state:

```php
BadgeColumn::make('role')
    ->formatStateUsing(fn (string $state) => match($state) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'editor' => 'Editor',
        default => ucfirst($state),
    })
    ->colors([
        'super_admin' => 'danger',
        'admin' => 'primary',
        'editor' => 'success',
    ])
```

## Size

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg — default md
```

`->xl()` exists on the shared size API, but the badge surface renders it with
the `md` padding; `lg` is the largest pill.

## Extended Example

A moderation table where one column carries three signals at once: the colour
comes from a map, the icon marks only the states that need attention, and the
label is rewritten for readers who do not think in slugs.

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;

class ArticleTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Article::class)
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold'),

                BadgeColumn::make('status') // [tl! focus:start]
                    ->colors([
                        'published' => 'success',
                        'in_review' => 'warning',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                    ])
                    ->icons([
                        'in_review' => 'clock',     // only the states that need a
                        'rejected' => 'x-circle',   // second glance get an icon
                    ])
                    ->formatStateUsing(fn (string $state) => str($state)->headline())
                    ->size('sm'), // [tl! focus:end]

                TextColumn::make('published_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->paginated();
    }

    public function render()
    {
        return view('livewire.article-table');
    }
}
```

## BadgeColumn API

The badge surface itself. Everything else a column can do — `->label()`,
`->sortable()`, `->visible()`, formatting, editing — is the shared column API,
documented in [Columns](index.md).

```php
->colors(array $map)                  // ['state' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)             // fn ($state) => 'color_name'|Color|null — beats the map
->icons(array $map)                   // ['state' => 'icon_name'|Icon, ...]
->iconUsing(Closure $fn)              // fn ($state) => 'icon_name'|Icon|null — beats the map
->color(string|Color $color)          // fallback colour when the state maps to nothing
->icon(string|Icon $icon)             // fallback icon when the state maps to nothing
->size(string|Size $size)             // 'xs'|'sm'|'md'|'lg' — default 'md'
->xs() / ->sm() / ->md() / ->lg()     // size presets
->getSize(): string
->getColorForState($state): ?string   // the resolved colour, whole ladder included
->getIconForState($state): ?string    // the resolved icon, whole ladder included
```

## Related

- [Columns](index.md) — the shared column API every column inherits
- [IconColumn](icon.md) — the same state ladder, rendered as an icon alone
- [PollColumn](poll.md) — a badge over a live-polled value
- [Theming](../../theming.md) — the colour vocabulary these maps draw from

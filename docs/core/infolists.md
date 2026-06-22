---
order: 55
---

# Infolists

Infolists are the read-only counterpart of a [form](../forms/overview.md): a declarative, fluent schema that **displays** a single record instead of editing it. They live in `wire-core` next to [widgets](widgets.md) and reuse the same schema layout (`Section`, `Grid`, `Fieldset`) and Foundation concerns (label, icon, color, size, visibility, column span) as forms and table columns — so one vocabulary spans the whole ecosystem.

```php
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Colors\Color;

Infolist::make()
    ->record($user)
    ->schema([
        Section::make('Profile')->icon('user')->columns(2)->schema([
            TextEntry::make('name')->weight('bold'),
            TextEntry::make('email')->icon('envelope')->copyable(),
            TextEntry::make('created_at')->dateTime()->since(),
            TextEntry::make('status')
                ->badge()
                ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray),
            IconEntry::make('is_verified')->boolean(),
        ]),
    ]);
```

> **New to this?** An infolist is just a list of things to show about one record. You build it in PHP, hand it a record, and echo it in Blade. The rest of this page builds up from the simplest possible example.

## Installation

Infolists ship with `wire-core` — nothing extra to install. Make sure the package views are in your Tailwind content paths so the styles are generated:

```js
export default {
    content: [
        // ...your app paths
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    ],
}
```

## Quick start

An infolist lives on a Livewire component. The simplest way is a [computed property](https://livewire.laravel.com/docs/computed-properties) that returns the `Infolist`, which you then echo in the component's view.

```php
use Livewire\Attributes\Computed;
use Livewire\Component;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Foundation\Schema\Section;

class ShowUser extends Component
{
    public User $user;          // the record you want to display

    #[Computed]
    public function infolist(): Infolist
    {
        return Infolist::make()
            ->record($this->user)               // 1. give it the record
            ->schema([                           // 2. list what to show
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),         // reads $user->name
                        TextEntry::make('email')->copyable(),
                    ]),
            ]);
    }

    public function render()
    {
        return view('livewire.show-user');
    }
}
```

```blade
{{-- resources/views/livewire/show-user.blade.php --}}
<div>
    {{ $this->infolist }}        {{-- 3. render it --}}
</div>
```

That's the whole loop: **record in → schema → echo out.** `{{ $this->infolist }}` works because an `Infolist` is `Htmlable` and `$this->infolist` resolves the computed property — no special helper or trait required.

> You can name the method anything (`$this->orderInfolist`, `$this->summary`, …) and have several on one component — see [Composing the schema](#composing-the-schema).

## Entry types at a glance

| Entry | Class | Use for |
|-------|-------|---------|
| **Text** | `TextEntry` | Text, numbers, money, dates — plus badge, copy, list, and truncation |
| **Icon** | `IconEntry` | Booleans and state → icon maps |
| **Image** | `ImageEntry` | Avatars and thumbnails (single or gallery) |
| **Color** | `ColorEntry` | A color swatch + its value |
| **Key-value** | `KeyValueEntry` | An array / JSON attribute as a key/value table |
| **Repeatable** | `RepeatableEntry` | A nested entry schema repeated per item of a relation/array |

> **Enum casts.** Entries read enum-cast attributes safely: `TextEntry` renders the enum label
> (via the `Enum\HasLabel` contract, else the backing value / case name), and `IconEntry`
> auto-resolves its icon and color from an enum implementing `Enum\HasColor` / `Enum\HasIcon`.
> See [Foundation → Enums](foundation.md#enums).

## Table of Contents

1. [The Infolist object](#the-infolist-object)
2. [Composing the schema](#composing-the-schema)
3. [State resolution](#state-resolution)
4. [Layout](#layout)
5. [TextEntry](#textentry)
6. [IconEntry](#iconentry)
7. [ImageEntry](#imageentry)
8. [ColorEntry](#colorentry)
9. [KeyValueEntry](#keyvalueentry)
10. [RepeatableEntry](#repeatableentry)
11. [Inside an action modal](#inside-an-action-modal)
12. [Infolist API](#infolist-api)

## The Infolist object

`Infolist::make()` builds the container; `record()` binds the data source (an Eloquent model **or** a plain array), `schema()` holds the entries and layout, and `columns()` sets the top-level grid.

```php
Infolist::make()
    ->record($order)        // Model|array
    ->columns(2)            // top-level grid columns (default 1)
    ->schema([ /* … */ ]);
```

`state(array $data)` is an alias for `record()` when the source is a plain array:

```php
Infolist::make()->state(['name' => 'Ada', 'email' => 'ada@example.com'])->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
]);
```

The record is propagated to every entry automatically when the infolist renders, recursing through layout components.

##  Composing the schema

> **Can I add more than a couple of fields?** Yes — `schema()` is just a list. Put as many entries as you like, group them with as many sections as you like, and mix any entry types together. There is no limit and no special wiring; you are only arranging objects in an array.

**Add as many entries as you need.** Each `make('column')` line shows one value:

```php
Section::make('Profile')->columns(2)->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
    TextEntry::make('phone'),
    TextEntry::make('created_at')->date(),
    IconEntry::make('is_verified')->boolean(),
    // ...add more, in any order
]);
```

**Use several sections** to break a record into logical groups — each is a separate card:

```php
Infolist::make()->record($order)->schema([
    Section::make('Customer')->columns(2)->schema([
        TextEntry::make('customer.name'),
        TextEntry::make('customer.email'),
    ]),
    Section::make('Payment')->columns(2)->schema([
        TextEntry::make('total')->money(),
        TextEntry::make('status')->badge(),
    ]),
    Section::make('Notes')->schema([
        TextEntry::make('notes')->prose(),
    ]),
]);
```

**Nest layouts** — a `Grid` or `Fieldset` can live inside a `Section`, and a `RepeatableEntry` carries its own sub-schema:

```php
Section::make('Order')->schema([
    Grid::make()->columns(3)->schema([
        TextEntry::make('number'),
        TextEntry::make('placed_at')->date(),
        TextEntry::make('total')->money(),
    ]),
    RepeatableEntry::make('items')->columns(3)->schema([
        TextEntry::make('label'),
        TextEntry::make('qty')->numeric(),
        TextEntry::make('price')->money(),
    ]),
]);
```

**You don't even need a section** — entries can sit directly in the infolist, arranged by the top-level `columns()`:

```php
Infolist::make()->record($user)->columns(2)->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
]);
```

**Several infolists on one page** — just define more than one computed property and echo each where you want it:

```php
#[Computed]
public function profile(): Infolist { /* ... */ }

#[Computed]
public function billing(): Infolist { /* ... */ }
```

```blade
<div class="space-y-6">
    {{ $this->profile }}
    {{ $this->billing }}
</div>
```

> **Rule of thumb:** if you can describe what to show as "this value, then that value, grouped under these headings", you can express it here — one entry per value, one section per group.

## State resolution

By default an entry reads its value from the record by **name**, with dot notation for relations and nested arrays (resolved via `data_get`):

```php
TextEntry::make('name');              // $record->name
TextEntry::make('company.name');      // $record->company->name
TextEntry::make('address.city');      // $record['address']['city']
```

Override resolution with `state()` (receives the record), transform the resolved value with `formatStateUsing()` (receives `$state, $record`), and provide a fallback with `default()`:

```php
TextEntry::make('full_name')
    ->state(fn ($record) => $record->first_name.' '.$record->last_name);

TextEntry::make('status')
    ->formatStateUsing(fn ($state) => ucfirst($state))
    ->default('—');
```

`color()`, and any other dynamic property, also accept a closure resolved with `$state` and `$record`:

```php
TextEntry::make('priority')
    ->badge()
    ->color(fn ($state) => match ($state) {
        'high' => Color::Danger,
        'medium' => Color::Warning,
        default => Color::Gray,
    });
```

## Layout

Infolists use the canonical schema layout from `NyonCode\WireCore\Foundation\Schema` — the same classes the form layouts subclass:

```php
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Fieldset;

Section::make('Billing')
    ->icon('credit-card')
    ->description('Plan and invoicing details')
    ->columns(2)
    ->collapsible()
    ->schema([
        Grid::make()->columns(3)->schema([ /* entries */ ]),
        Fieldset::make('Tax')->schema([ /* entries */ ]),
    ]);
```

Every entry accepts `columnSpan(int)` / `columnSpanFull()` to span the grid.

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

## Inside an action modal

A `ViewAction` (or any action) can open a read-only modal that shows the record in an infolist. `infolist()` mirrors `form()`: the action's record is bound automatically, the modal is **not** a confirmation, and it renders only a close button.

```php
use NyonCode\WireCore\Actions\ViewAction;

ViewAction::make()
    ->slideOver()
    ->infolist([
        TextEntry::make('name')->weight('bold'),
        TextEntry::make('email')->copyable(),
        TextEntry::make('created_at')->dateTime()->since(),
    ]);

// Closure form receives the record:
ViewAction::make()->infolist(fn ($record) => Infolist::make()->schema([
    TextEntry::make('name'),
]));
```

## Infolist API

| Method | Description |
|--------|-------------|
| `make()` | Create an infolist |
| `record(Model\|array)` | Bind the data source |
| `state(array)` | Bind a plain array (alias of `record()`) |
| `schema(array)` | Entries and layout components |
| `columns(int)` | Top-level grid columns (default 1) |
| `getRecord()` / `getSchema()` / `getColumns()` | Accessors |
| `toHtml()` | Render (also via `Htmlable` echo) |

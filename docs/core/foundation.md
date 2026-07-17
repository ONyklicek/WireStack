---
order: 20
---

# Foundation

Foundation is the permanent core of `wire-core`. It provides shared traits, base classes, icons, colors, and Blade components used by all other modules and packages.

## Concerns (Traits)

### Component Configuration

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasLabel` | `label($label)`, `translateLabel()`, `getLabel()` | Display label |
| `HasDescription` | `description($text)`, `getDescription()` | Description text |
| `HasHelperText` | `helperText($text)`, `getHelperText()` | Helper text below field |
| `HasHint` | `hint($text)`, `hintIcon($icon)`, `getHint()` | Hint text/icon |
| `HasName` | `name($name)`, `getName()` | Identifier name |
| `HasDefault` | `default($value)`, `getDefault()` | Default value |
| `HasIcon` | `icon($name, $position)`, `getIcon()` | Icon by name (`pencil`, or `prefix:name`) |
| `HasColor` | `color($color)`, `getColor()` | Tailwind color name |
| `HasSize` | `size($size)`, `getSize()` | Size variant (sm/md/lg/xl) |
| `HasColumns` | `columnSpan($span)`, `columnStart($start)` | Grid column layout |
| `HasExtraAttributes` | `extraAttributes(array $attrs)` | Arbitrary HTML attributes |

### State & Behavior

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasState` | `state($value)`, `getState()`, `live()`, `debounce($ms)` | Livewire state binding |
| `HasVisibility` | `hidden($condition)`, `visible($condition)`, `isHidden()` | Conditional visibility |
| `HasDisabled` | `disabled($condition)`, `isDisabled()` | Disabled state |
| `HasValidation` | `required()`, `rules($rules)`, `validationMessages($msgs)` | Validation rules |

### Infrastructure

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasMake` | `static make(...$args)` | Static factory |
| `HasEvaluate` | `evaluate($value, $params)` | Closure-or-value evaluation with DI |
| `HasSchema` | `schema(array $components)`, `getSchema()` | Child component array |
| `HasHtmlAttributes` | `htmlAttributes()`, `getHtmlAttributes()` | Merged HTML attrs |
| `EvaluatesClosures` | `evaluate($value, $record, ...)` | Per-record Closure resolution |

### Action-specific

| Trait | Methods | Description |
|-------|---------|-------------|
| `HasDynamicProperties` | `resolve($record)` | Per-record property resolution |
| `HasKeyboardShortcut` | `keyboardShortcut($keys)` | Alpine.js keyboard binding |
| `HasLifecycle` | `before($fn)`, `after($fn)`, `halt()` | Before/after hooks with halt |
| `HasLoadingState` | `loadingIndicator()`, `debounce($ms)` | Loading UI state |
| `HasModal` | `requiresConfirmation()`, `modalHeading()`, `slideOver()`, ... | Modal config |

> Button/badge CSS classes come from the canonical `HasColor` resolvers (see
> [Canonical color resolvers](#canonical-color-resolvers-hascolor)), not from a
> per-component map. `HasButtonStyles` remains only as a deprecated alias.

### Closure Evaluation

All configuration methods accept both scalar values and Closures:

```php
// Scalar
TextColumn::make('name')->label('Full Name');

// Closure — evaluated per record at render time
TextColumn::make('name')->label(fn (User $record) => "Name: {$record->name}");

// Closure with dependency injection
Action::make('edit')->hidden(fn (User $record, Table $table) => ! $table->isEditable());
```

## Base Classes

| Class | Namespace | Description |
|-------|-----------|-------------|
| `Component` | `Foundation\Components` | Abstract base — `make()`, `name`, `key` |
| `ViewComponent` | `Foundation\Components` | Component that renders a Blade view |
| `LayoutComponent` | `Foundation\Components` | Component with child `schema()` |

```php
// All components use the static factory pattern
$field = TextInput::make('email');
$column = TextColumn::make('name');
$action = Action::make('delete');
```

## Icons

The complete [Heroicons](https://heroicons.com) **solid** collection (324 icons,
`20x20` viewBox) is bundled inline — no external dependencies, no extra package.
It is the **default set**, addressed with bare names (`pencil`, `user`). You can
register any number of additional sets (Lucide, Feather, your own brand icons)
alongside it — see [Using multiple icon sets](#using-multiple-icon-sets).

Each icon carries its own `viewBox` and fill/stroke styling, so 20×20 fill-based
Heroicons and 24×24 stroke-based sets render correctly side by side.

### Blade Usage

```blade
<x-wire::icon name="check" class="w-5 h-5" />
<x-wire::icon name="trash" class="w-4 h-4 text-red-500" />

{{-- A prefixed icon from another registered set --}}
<x-wire::icon name="lucide:home" class="w-5 h-5" />

{{-- Expose to assistive tech (otherwise the icon is aria-hidden) --}}
<x-wire::icon name="trash" label="Delete" />
```

### PHP Usage

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

$manager = app(IconManager::class);

$manager->render('check');                 // full <svg> string
$manager->render('trash', 'w-5 h-5', 'text-red-500', label: 'Delete');
$manager->has('lucide:home');              // bool
$manager->resolve('check');                // ?ResolvedIcon (body + viewBox + attrs)
$manager->allNames();                      // every available name (prefixed for non-default sets)
```

`render()` is the canonical entry point — it applies each icon's own `viewBox` and
styling. `getPath()` returns just the inner markup and is kept only for callers
that wrap their own `<svg>` (correct only for `0 0 20 20` fill icons).

### Available Icons

Every default icon uses its **canonical Heroicons name** — the file name from
[heroicons.com](https://heroicons.com) (solid variant). Browse the full set
there; a few examples:

`academic-cap`, `arrow-down-tray`, `bars-3`, `chevron-up`, `cog-6-tooth`,
`document-text`, `envelope`, `funnel`, `magnifying-glass`, `pencil`, `qr-code`,
`trash`, `user`, `wrench-screwdriver`, `x-mark`.

For IDE autocompletion you can reference icons through the `Icon` enum instead of
raw strings:

```php
use NyonCode\WireCore\Foundation\Icons\Icon;

Action::make('edit')->icon(Icon::pencilSquare);
```

### Wire-friendly Aliases

A small set of short aliases map onto canonical icons for convenience:

| Alias | Resolves to | Alias | Resolves to |
|-------|-------------|-------|-------------|
| `pen`, `edit` | `pencil` | `delete` | `trash` |
| `view` | `eye` | `add` | `plus` |
| `download`, `export` | `arrow-down-tray` | `upload`, `import` | `arrow-up-tray` |
| `duplicate`, `copy` | `document-duplicate` | `x`, `close` | `x-mark` |
| `settings` | `cog` | `mail`, `email` | `envelope` |
| `exclamation`, `warning` | `exclamation-triangle` | `information`, `info` | `information-circle` |
| `question` | `question-mark-circle` | `archive` | `archive-box` |
| `refresh` | `arrow-path` | `shield` | `shield-check` |
| `lock` | `lock-closed` | `filter` | `funnel` |
| `more`, `dots-vertical` | `ellipsis-vertical` | `dots-horizontal` | `ellipsis-horizontal` |
| `external-link` | `arrow-top-right-on-square` | | |

### Accessibility

Icons render as decorative by default (`aria-hidden="true"`). Pass a `label` when
the icon carries meaning on its own — it is then exposed as an image with that
label (`role="img"` + `aria-label`):

```blade
<x-wire::icon name="check-circle" label="Verified" />
```

## Adding Custom Icons

You don't have to settle for the bundled set. Pick whichever approach fits. Custom
icons (folders and inline) are **bare-named** and take priority over the default
set, so a custom icon is used anywhere a name is accepted
(`->icon('logo')`, `<x-wire::icon name="logo" />`, …).

When you paste a complete `<svg>…</svg>`, its `viewBox` and styling attributes
(`fill`, `stroke`, `stroke-width`, …) are **preserved** — so you can drop in icons
from any source and format. A bare `<path>` fragment defaults to the Heroicons
solid format (`0 0 20 20`, `fill="currentColor"`).

### 1. From a folder of SVG files (easiest)

Drop `.svg` files in a directory and register the path — the file name becomes the
icon name (`logo.svg` → `logo`). No class, no boilerplate.

Via config (`config/wire-core.php`), great for app-wide icons. A string key adds a
dash-joined name prefix and avoids file-name collisions between folders:

```php
'icons' => [
    'paths' => [
        resource_path('icons'),                 // resources/icons/logo.svg => "logo"
        'brand' => resource_path('icons/brand'), // icons/brand/mark.svg   => "brand-mark"
    ],
],
```

Or at runtime:

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

app(IconManager::class)->registerIconsFromDirectory(
    resource_path('icons/brand'),
    prefix: 'brand',                   // brand/logo.svg => "brand-logo"
);
```

> The folder `prefix` produces a **flat name** (`brand-logo`) — it is not the same
> as the `prefix:name` set namespace described below.

### 2. Inline, by name

Register individual icons — paste a full `<svg>…</svg>` (the wrapper is stripped,
its viewBox/styling preserved) or just the inner `<path>`:

```php
app(IconManager::class)->registerIcons([
    'logo'  => '<svg viewBox="0 0 20 20"><path d="M10 2 …"/></svg>',
    'spark' => '<path d="M10 1 12 8 …"/>',
]);
```

Reuse the same name as a bundled icon to **override** it. Put the call in a service
provider's `boot()` so the icons are available everywhere:

```php
public function boot(): void
{
    app(IconManager::class)->registerIconsFromDirectory(resource_path('icons'));
}
```

### 3. A reusable icon set (advanced)

For a complete, swappable style, implement `IconSet`. Implement the optional
`ProvidesIconMetadata` capability too if your icons are stroke-based or use a
non-`20x20` viewBox (Lucide, Feather, Heroicons outline) — that lets each icon
carry its own `ResolvedIcon` (body + viewBox + attributes):

```php
use NyonCode\WireCore\Foundation\Icons\{IconSet, ProvidesIconMetadata, ResolvedIcon};

final class LucideIconSet implements IconSet, ProvidesIconMetadata
{
    private string $dir = '/abs/path/to/node_modules/lucide-static/icons';

    public function getIcon(string $name): ?ResolvedIcon
    {
        $file = "{$this->dir}/{$name}.svg";

        // fromSvg() keeps Lucide's viewBox="0 0 24 24" + fill=none stroke=currentColor.
        return is_file($file) ? ResolvedIcon::fromSvg(file_get_contents($file)) : null;
    }

    public function getPath(string $name): ?string { return $this->getIcon($name)?->body; }
    public function has(string $name): bool        { return is_file("{$this->dir}/{$name}.svg"); }
    public function names(): array                 { /* basenames of *.svg */ return []; }
}
```

Sets that implement only `IconSet` still work — their `getPath()` output is wrapped
in the default `0 0 20 20` fill format.

## Using multiple icon sets

Resolution is **deterministic and namespaced**:

- The **default set is unprefixed** — `pencil`, `user`, `lucide` aliases, custom
  icons — and is always Heroicons unless you swap it (below).
- **Every other set requires a unique prefix** and is addressed as `prefix:name`.

Register additional sets in config under their prefix key:

```php
// config/wire-core.php
'icons' => [
    'default_set' => 'default',
    'sets' => [
        'default' => DefaultIconSet::class,   // → "pencil"      (Heroicons, 20×20 fill)
        'lucide'  => LucideIconSet::class,    // → "lucide:home" (24×24 stroke)
        'custom'  => App\Wire\Icons\MyIconSet::class,
    ],
],
```

```blade
<x-wire::icon name="pencil" />        {{-- Heroicons --}}
<x-wire::icon name="lucide:home" />   {{-- Lucide --}}
```

This guarantees the sets never collide: a bare name is always the default set, a
prefixed name is always that exact set. Because of this, **registering a non-default
set without a prefix throws** an `InvalidArgumentException`:

```php
app(IconManager::class)->registerIconSet(new LucideIconSet, 'lucide'); // ok
app(IconManager::class)->registerIconSet(new LucideIconSet);           // throws
```

> The separator is a colon (`:`). Icon names themselves use dashes
> (`arrow-down-tray`), so there is no ambiguity. Use `default:name` to address the
> base set explicitly.

### Swapping the default (unprefixed) set

To make a different set the unprefixed base — e.g. ship Lucide as your primary
style — point `default_set` at its key:

```php
'icons' => [
    'default_set' => 'lucide',            // bare names now resolve against Lucide
    'sets' => [
        'lucide'  => LucideIconSet::class,
        'default' => DefaultIconSet::class, // still available as "default:pencil"
    ],
],
```

At runtime: `app(IconManager::class)->setDefaultIconSet(new LucideIconSet)`.

### Catching typos

Set `icons.warn_missing` (or `WIRE_ICONS_WARN_MISSING=true`) to log a warning
whenever an unknown icon name is rendered — it still renders the fallback
placeholder, but the log helps surface typos in development.

### Regenerating the bundled Heroicons

The bundled paths live in the generated PHP data file
`packages/core/resources/icons/heroicons-solid.php`, produced from the official
`heroicons` npm package (`20/solid` SVGs, keyed by file name). Regenerate that
file rather than editing icon paths by hand.

## Colors

`->color()` accepts the **complete Tailwind palette** on every surface. Two vocabularies
resolve through the same canonical map:

<div class="wire-swatches"><p class="wire-swatch-group">Semantic roles</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: var(--primary)"></span><span class="wire-swatch__name">primary</span><span class="wire-swatch__alias">your accent</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6b7280"></span><span class="wire-swatch__name">gray</span><span class="wire-swatch__alias">secondary</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #10b981"></span><span class="wire-swatch__name">success</span><span class="wire-swatch__alias">emerald</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">danger</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f59e0b"></span><span class="wire-swatch__name">warning</span><span class="wire-swatch__alias">amber</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">info</span></div></div><p class="wire-swatch-group">Raw hue families</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #3b82f6"></span><span class="wire-swatch__name">blue</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #22c55e"></span><span class="wire-swatch__name">green</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">red</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #eab308"></span><span class="wire-swatch__name">yellow</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">cyan</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #64748b"></span><span class="wire-swatch__name">slate</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #71717a"></span><span class="wire-swatch__name">zinc</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #737373"></span><span class="wire-swatch__name">neutral</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #78716c"></span><span class="wire-swatch__name">stone</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f97316"></span><span class="wire-swatch__name">orange</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #84cc16"></span><span class="wire-swatch__name">lime</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #14b8a6"></span><span class="wire-swatch__name">teal</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #0ea5e9"></span><span class="wire-swatch__name">sky</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6366f1"></span><span class="wire-swatch__name">indigo</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #8b5cf6"></span><span class="wire-swatch__name">violet</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #a855f7"></span><span class="wire-swatch__name">purple</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #d946ef"></span><span class="wire-swatch__name">fuchsia</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ec4899"></span><span class="wire-swatch__name">pink</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f43f5e"></span><span class="wire-swatch__name">rose</span></div></div><p class="wire-swatch-group">Achromatic (adaptive)</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ffffff; box-shadow: inset 0 0 0 1px #d1d5db"></span><span class="wire-swatch__name">white</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #000000"></span><span class="wire-swatch__name">black</span></div></div></div>

**Semantic roles** — fixed brand hues that carry meaning:

| Name | Resolves to |
|------|-------------|
| `primary` | Brand primary |
| `success` (alias `emerald`) | Emerald |
| `danger` | Red |
| `warning` (alias `amber`) | Amber |
| `info` | Cyan |
| `gray` (alias `secondary`) | Neutral gray |

**Raw hue families** — every Tailwind color, for finer control:

`blue`, `green`, `red`, `yellow`, `cyan`, `slate`, `zinc`, `neutral`, `stone`,
`orange`, `lime`, `teal`, `sky`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`,
`rose`.

> **Literal hues are not aliases.** `blue`, `green` and `yellow` are their own
> literal Tailwind hues — `blue` is distinct from the re-themeable brand `primary`,
> `green` from `success`/`emerald`, and `yellow` from `warning`/`amber`. `red` and
> `cyan` render the same hue as `danger`/`info` but stay available by name.

**Achromatic endpoints** — `white` and `black`. Tailwind has no numeric
`white`/`black` scale, so these resolve **adaptively**: `black` is a dark ink/fill in
light mode and flips to white in dark mode, `white` is the inverse — so they stay
readable on both themes.

```php
Action::make('delete')->color('danger');   // semantic role
Action::make('archive')->color('teal');     // raw hue
BadgeColumn::make('status')->colors([
    'active' => 'success',
    'pending' => 'warning',
    'inactive' => 'danger',
]);
```

The type-safe `Foundation\Colors\Color` enum has a case for every one of these
(`Color::Danger`, `Color::Teal`, …). Each color resolves to Tailwind utility classes
for bg, text, border, ring, and hover variants — the same value renders identically
on a badge, a solid/outlined/link button, a modal, a choice card, and a chart bar.

### Canonical color resolvers (`HasColor`)

`Foundation\Concerns\HasColor` is the **single source of truth** for color → Tailwind
class mapping. Every surface delegates to it instead of re-encoding `match` maps,
so a semantic color resolves to the same hue everywhere (`success` → emerald,
`info` → cyan, `primary` → your brand accent), while literal hues like `blue`,
`green` and `yellow` and the adaptive `white`/`black` endpoints resolve to
themselves.

| Resolver | Surface |
|----------|---------|
| `getSolidColorClasses()` | filled button (bg + text + hover + focus + dark) |
| `getOutlinedColorClasses()` | outlined button |
| `getGhostColorClasses()` | dropdown / menu item |
| `getIconButtonColorClasses()` | icon-only button |
| `getLinkColorClasses()` | text/link button (underline on hover) |
| `getSolidBgClass()` / `getSoftBgClass()` | bare fill only (toggle on/off track, count badge) |
| `getBadgeColorClasses()` | soft "pill" badge (bg + text) |
| `getTextColorClasses()` | foreground-only text tint |
| `getChoiceColorClasses()` | radio/segmented/card selected state bundle |
| `getModalSubmitButtonClasses()` | modal confirm/submit button |
| `getModalIconBgClass()` / `getModalIconTextClass()` | modal icon chip |
| `getGradientFillClasses()` / `getFillTextClasses()` | bar-chart fill + accent (literal chart hues) |

When adding a color or surface, extend the resolver here once — downstream
columns, badges, actions, and toggles pick it up automatically. Keep utility
names compatible with the lowest supported Tailwind version (see
[ADR 0005](https://github.com/ONyklicek/WireStack/blob/main/architecture/decisions/0005-tailwind-4-support.md)); use only
standard hue names, never version-specific ones.

### Canonical sizing & typography resolvers

Sibling single-source resolvers, used the same way as `HasColor` — extend once,
every surface picks it up, and class strings stay literal for Tailwind's JIT
scanner.

| Resolver | Surface |
|----------|---------|
| `HasSize::getBadgeSizeClasses($size)` | soft "pill"/badge padding + font size |
| `HasSize::getButtonSizeClasses($size, $iconOnly)` | button padding scale (action buttons, action-group triggers, `ButtonColumn`); `$iconOnly` returns square padding |
| `HasFontWeight::getFontWeightClasses($weight)` | `font-*` weight utility (table columns, infolist entries); unknown weight → `font-normal` |
| `Modals\Concerns\HasModalProperties::getMaxWidthClass($width, $responsive)` | modal `max-w-*` (centered dialogs gate at `sm:`; slide-overs pass `responsive: false`) |

### Type-safe value enums

Every fluent setter that takes a string token **also accepts a canonical enum** from
`Foundation\Enums\` — `->size('lg')` and `->size(Size::Lg)` are interchangeable, and the
string form stays fully supported. Each enum is the single owner of its vocabulary
(`values()` + `resolve()`), so a token resolves to the same utility on every surface, and
unknown tokens fall back to a sensible default instead of emitting an unscannable class.

| Enum | Tokens | Setters that accept it |
|------|--------|------------------------|
| `Colors\Color` | semantic roles + every raw hue (see [Colors](#colors)) | `->color()` everywhere |
| `Enums\Breakpoint` | `sm` `md` `lg` `xl` `2xl` | column `->visibleFrom()` / `->hiddenFrom()` / `->mobileBreakpoint()`, `Table::stackedOnMobile()`, `->mobileBreakpoint()` on sheets/modals, `Grid` per-breakpoint `columns` keys |
| `Enums\Size` | `xs` `sm` `md` `lg` `xl` | `->size()` (+ `->sm()`/`->md()`/… shortcuts) on actions, buttons, badge/icon columns |
| `Enums\FontWeight` | `thin` `extralight` `light` `normal` `medium` `semibold` `bold` `extrabold` `black` | column `->weight()`, infolist `TextEntry::weight()` |
| `Enums\Alignment` | `left` `center` `right` | column `->alignment()`, `Table::actionsAlignment()` |
| `Enums\IconPosition` | `before` `after` | `->icon($icon, $position)` on actions, buttons, fields |
| `Enums\Placement` | `bottom-start` `bottom-end` `top-start` `top-end` | `ActionGroup::dropdownPosition()` |
| `Enums\ModalWidth` | `sm` `md` `lg` `xl` `2xl` … `7xl` `full` | `->width()` / `->modalWidth()` on modals, slide-overs, action modals |

```php
use NyonCode\WireCore\Foundation\Enums\{Alignment, Breakpoint, ModalWidth, Size};

TextColumn::make('email')->visibleFrom(Breakpoint::Md)->alignment(Alignment::Right);
Action::make('edit')->size(Size::Lg)->modalWidth(ModalWidth::TwoXl);
```

The `Breakpoint`, `Alignment` and `Placement` enums additionally own the **literal** Tailwind
classes their tokens map to (`Breakpoint::Md->tableCellClass()`, `Alignment::Right->textClass()`,
`Placement::TopEnd->originClass()`), so the class map has one owner and Blade consumes a scannable
utility instead of interpolating `text-{$align}`.

## Enums

PHP enums cannot be stringified with `(string) $enum`, yet Eloquent enum casts hand the raw
instance to every display and state surface. `EnumResolver` is the single canonical owner that
normalizes such values; downstream packages (table, forms, infolists, exports) delegate to it
instead of re-encoding `(string) $enum` or local `match` maps.

```php
use NyonCode\WireCore\Foundation\Support\EnumResolver;

EnumResolver::scalar($value);   // backed enum → ->value, unit enum → case name, else passthrough
EnumResolver::label($value);    // getLabel() → label() method → headline(case name); non-enum passthrough
EnumResolver::display($value);  // label() + array/JSON → compact JSON; (string)-safe everywhere
EnumResolver::color($value);    // HasColor → getColor(), else null
EnumResolver::icon($value);     // HasIcon  → getIcon(),  else null
EnumResolver::isEnum($value);   // bool — is this an enum instance?

EnumResolver::isEnumClass($value);       // bool — is this an enum class-string?
EnumResolver::options(Status::class);    // [value => label] map from the enum's cases
EnumResolver::normalizeOptions($value);  // enum class → options() map; arrays pass through
```

Use `scalar()` for map keys, comparisons and copy values; `display()` (or `label()`) wherever a
value is shown. Non-enum values always pass through untouched, so the helpers are safe to call on
anything.

`options()` powers the Filament-style enum-as-options shorthand: any option-based surface —
form `Select` / `Radio` / `CheckboxList` (via the shared `WireForms\Concerns\HasOptions` trait),
table `SelectColumn` and `SelectFilter`, plus the generic `Column::editable()` / `filterable()` /
`filterAsSelect()` — accepts `->options(Status::class)` and delegates the expansion here. Each case
keys by `scalar()` and labels through the same canonical `label()` resolution, so an option reads
identically to the matching display cell. A single-value form field whose options come from an enum
also gains an automatic `in:` validation rule (see [Forms → Select](../forms/fields/select.md#enum-options)).

### Opt-in enum contracts

An enum used as a cast may implement any of these to drive richer rendering. They live under
`Foundation\Contracts\Enum\` and are **distinct** from the builder-facing `Foundation\Contracts\HasLabel`
/ `HasIcon` (which carry fluent setters for components).

| Contract | Method | Effect |
|----------|--------|--------|
| `Enum\HasLabel` | `getLabel(): ?string` | Display surfaces render this label instead of the default headline of the case name |
| `Enum\HasColor` | `getColor(): string\|Color\|null` | `BadgeColumn` / `IconColumn` / `IconEntry` auto-resolve the color |
| `Enum\HasIcon` | `getIcon(): string\|Icon\|null` | The same surfaces auto-resolve the icon |

```php
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function getLabel(): ?string        { return ucfirst($this->value); }
    public function getColor(): string|Color|null
    {
        return $this === self::Paid ? Color::Success : Color::Warning;
    }
}
```

See [Table → Enum & JSON Casts](../table/columns/casts.md) for column-level usage.

## Support Utilities

| Class | Description |
|-------|-------------|
| `EvaluatesClosures` | Trait — evaluates Closure-or-value with parameter injection |
| `ArrayDotHelper` | Dot-notation access: `get('user.name', $array)`, `set()`, `has()`, `forget()` |
| `EnumResolver` | Static — canonical enum/array normalizer (`scalar`, `label`, `display`, `color`, `icon`, `options`) |

## Blade Components

Foundation provides base components under the `wire::` namespace:

```blade
{{-- Icon --}}
<x-wire::icon name="check" />

{{-- Badge --}}
<x-wire::badge color="success">Active</x-wire::badge>

{{-- Button --}}
<x-wire::button color="primary" size="sm">Save</x-wire::button>

{{-- Dropdown --}}
<x-wire::dropdown>
    <x-slot:trigger>Options</x-slot:trigger>
    <x-wire::dropdown.item>Edit</x-wire::dropdown.item>
    <x-wire::dropdown.item>Delete</x-wire::dropdown.item>
</x-wire::dropdown>
```

## Layout Components

A canonical layout vocabulary lives in `NyonCode\WireCore\Foundation\Schema\*` and is shared by both
**forms** and **infolists** (the forms `Layout\*` classes extend the core versions). Use these in any
`->schema([...])` array instead of ad-hoc Blade grids.

| Component | Purpose |
|-----------|---------|
| `Grid` | Responsive column grid |
| `Section` | Titled card with heading/description |
| `Fieldset` | Bordered group with a legend |
| `Flex` | Side-by-side flexbox row that stacks on mobile |
| `Tabs` / `Tab` | Tabbed panels |
| `Wizard` / `Step` | Multi-step layout |
| `Callout` | Soft colored notice box |
| `EmptyState` | Icon + heading + description + actions |

```php
use NyonCode\WireCore\Foundation\Schema\{Grid, Section, Flex, Callout};

Section::make('Team')
    ->description('People with access.')
    ->schema([
        // Int reflow, or a Filament-style per-breakpoint map.
        Grid::make()->columns(['default' => 1, 'md' => 2, 'lg' => 3])->schema([...]),
    ]);

// Flex: control distribution, alignment, spacing, wrap and child growth.
Flex::make()->from('md')->justify('between')->align('center')->gap(6)->wrap()->grow(false)->schema([...]);

// Callout — color hues delegate to the canonical alert palette.
Callout::make()->warning()->heading('Heads up')->icon('exclamation-triangle')->dismissible()
    ->content('Something worth noticing.');
```

`Callout` is the shared owner of the notice surface; the forms `Alert` field is its field-style alias.
Column counts (`Grid`, `CheckboxList`, `Section`, …) accept an int **or** a per-breakpoint map keyed by
`default`/`sm`/`md`/`lg`/`xl`/`2xl`.

### Standalone Blade tags

The same layouts are also exposed as slot-based `wire::` tags for plain Blade views (no schema array):

```blade
<x-wire::callout color="warning" heading="Storage almost full" icon="exclamation-triangle" dismissible>
    You have used 95% of your quota.
</x-wire::callout>

<x-wire::grid :columns="['default' => 1, 'md' => 2, 'lg' => 3]" gap="gap-3">…</x-wire::grid>

<x-wire::flex from="md" justify="between" align="center" :gap="4">…</x-wire::flex>

<x-wire::section heading="Profile" description="Basic info">…</x-wire::section>
<x-wire::fieldset legend="Billing address">…</x-wire::fieldset>

<x-wire::empty-state icon="outline:inbox" heading="No invoices yet" description="They will show up here.">
    <button>New invoice</button> {{-- slot becomes the action row --}}
</x-wire::empty-state>

{{-- Alpine-driven; client-side state only (no per-step validation) --}}
<x-wire::tabs>
    <x-wire::tab label="Profile">…</x-wire::tab>
    <x-wire::tab label="Security">…</x-wire::tab>
</x-wire::tabs>

<x-wire::wizard>
    <x-wire::step label="Account">…</x-wire::step>
    <x-wire::step label="Confirm">…</x-wire::step>
</x-wire::wizard>
```

For validated multi-step flows use action-modal wizards (`HasModal::steps()`) or the form schema `Wizard`
instead — the standalone `<x-wire::tabs>` / `<x-wire::wizard>` only switch panels client-side.

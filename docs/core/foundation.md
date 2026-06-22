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

Tailwind CSS color abstraction — 7 semantic color names:

| Name | Typical Mapping |
|------|-----------------|
| `primary` | Blue (brand) |
| `secondary` | Gray |
| `success` | Green |
| `danger` | Red |
| `warning` | Amber/Yellow |
| `info` | Cyan/Sky |
| `gray` | Neutral gray |

```php
Action::make('delete')->color('danger');
BadgeColumn::make('status')->colors([
    'active' => 'success',
    'pending' => 'warning',
    'inactive' => 'danger',
]);
```

Each color resolves to Tailwind utility classes for bg, text, border, ring, and hover variants.

### Canonical color resolvers (`HasColor`)

`Foundation\Concerns\HasColor` is the **single source of truth** for color → Tailwind
class mapping. Every surface delegates to it instead of re-encoding `match` maps,
so a semantic color resolves to the same hue everywhere (`success` → emerald,
`info` → cyan, `blue`/`primary` → primary).

| Resolver | Surface |
|----------|---------|
| `getSolidColorClasses()` | filled button (bg + text + hover + focus + dark) |
| `getOutlinedColorClasses()` | outlined button |
| `getGhostColorClasses()` | dropdown / menu item |
| `getIconButtonColorClasses()` | icon-only button |
| `getLinkColorClasses()` | text/link button (underline on hover) |
| `getSolidBgClass()` | bare fill only (toggle track, count badge) |
| `getBadgeColorClasses()` | soft "pill" badge (bg + text) |
| `getTextColorClasses()` | foreground-only text tint |
| `getModalIconBgClass()` / `getModalIconTextClass()` | modal icon chip |

When adding a color or surface, extend the resolver here once — downstream
columns, badges, actions, and toggles pick it up automatically. Keep utility
names compatible with the lowest supported Tailwind version (see
[ADR 0005](../../architecture/decisions/0005-tailwind-4-support.md)); use only
standard hue names, never version-specific ones.

## Enums

PHP enums cannot be stringified with `(string) $enum`, yet Eloquent enum casts hand the raw
instance to every display and state surface. `EnumResolver` is the single canonical owner that
normalizes such values; downstream packages (table, forms, infolists, exports) delegate to it
instead of re-encoding `(string) $enum` or local `match` maps.

```php
use NyonCode\WireCore\Foundation\Support\EnumResolver;

EnumResolver::scalar($value);   // backed enum → ->value, unit enum → case name, else passthrough
EnumResolver::label($value);    // HasLabel → getLabel(), else scalar() — human display text
EnumResolver::display($value);  // label() + array/JSON → compact JSON; (string)-safe everywhere
EnumResolver::color($value);    // HasColor → getColor(), else null
EnumResolver::icon($value);     // HasIcon  → getIcon(),  else null
EnumResolver::isEnum($value);   // bool
```

Use `scalar()` for map keys, comparisons and copy values; `display()` (or `label()`) wherever a
value is shown. Non-enum values always pass through untouched, so the helpers are safe to call on
anything.

### Opt-in enum contracts

An enum used as a cast may implement any of these to drive richer rendering. They live under
`Foundation\Contracts\Enum\` and are **distinct** from the builder-facing `Foundation\Contracts\HasLabel`
/ `HasIcon` (which carry fluent setters for components).

| Contract | Method | Effect |
|----------|--------|--------|
| `Enum\HasLabel` | `getLabel(): ?string` | Display surfaces render the label instead of the backing value / case name |
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

See [Table → Enum & JSON Casts](../table/columns.md#enum-json-casts) for column-level usage.

## Support Utilities

| Class | Description |
|-------|-------------|
| `EvaluatesClosures` | Trait — evaluates Closure-or-value with parameter injection |
| `ArrayDotHelper` | Dot-notation access: `get('user.name', $array)`, `set()`, `has()`, `forget()` |
| `EnumResolver` | Static — canonical enum/array normalizer (`scalar`, `label`, `display`, `color`, `icon`) |

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

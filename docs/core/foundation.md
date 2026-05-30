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
| `HasIcon` | `icon($name, $position)`, `getIcon()` | Heroicon SVG |
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
| `HasButtonStyles` | `getSolidColorClasses()`, `getOutlinedColorClasses()` | Button CSS classes |

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

### Blade Usage

```blade
<x-wire::icon name="check" class="w-5 h-5" />
<x-wire::icon name="trash" class="w-4 h-4 text-red-500" />
```

### PHP Usage

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

$svg = app(IconManager::class)->render('check');
```

### Available Icons

Every icon uses its **canonical Heroicons name** — the file name from
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

## Adding Custom Icons

You don't have to settle for the bundled set. Pick whichever approach fits — they
all add to (or override) the defaults, and a custom icon is used anywhere an icon
name is accepted (`->icon('logo')`, `<x-wire::icon name="logo" />`, …).

Icons are wrapped in a `0 0 20 20` viewBox, so design custom SVGs on a 20×20 grid
for consistent sizing.

### 1. From a folder of SVG files (easiest)

Drop `.svg` files in a directory and register the path — the file name becomes the
icon name (`logo.svg` → `logo`). No class, no boilerplate.

Via config (`config/wire-core.php`), great for app-wide icons:

```php
'icons' => [
    'paths' => [
        resource_path('icons'),        // resources/icons/logo.svg => "logo"
    ],
],
```

Or at runtime, with an optional prefix to namespace the set:

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

app(IconManager::class)->registerIconsFromDirectory(
    resource_path('icons/brand'),
    prefix: 'brand',                   // brand/logo.svg => "brand-logo"
);
```

### 2. Inline, by name

Register individual icons — paste a full `<svg>…</svg>` (the wrapper is stripped
automatically) or just the inner `<path>`:

```php
app(IconManager::class)->registerIcons([
    'logo'  => '<svg viewBox="0 0 20 20"><path d="M10 2 …"/></svg>',
    'spark' => '<path d="M10 1 12 8 …"/>',
]);
```

Reuse the same name as a bundled icon to **override** it.

Put either call in a service provider's `boot()` method so the icons are
available everywhere:

```php
public function boot(): void
{
    app(IconManager::class)->registerIconsFromDirectory(resource_path('icons'));
}
```

### 3. A reusable icon set (advanced)

For a complete, swappable theme (e.g. a different icon style), implement
`IconSet` and register the class in config under `icons.sets`. Sets registered
here take priority over the bundled defaults:

```php
use NyonCode\WireCore\Foundation\Icons\IconSet;

final class MyIconSet implements IconSet
{
    public function getPath(string $name): ?string { /* return inner SVG or null */ }
    public function has(string $name): bool { /* … */ }
    public function names(): array { /* … */ }
}
```

```php
// config/wire-core.php
'icons' => [
    'sets' => [
        'default' => DefaultIconSet::class,
        'custom'  => App\Wire\Icons\MyIconSet::class,
    ],
],
```

You can also register a set at runtime: `app(IconManager::class)->registerIconSet(new MyIconSet)`.

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

## Support Utilities

| Class | Description |
|-------|-------------|
| `EvaluatesClosures` | Trait — evaluates Closure-or-value with parameter injection |
| `ArrayDotHelper` | Dot-notation access: `get('user.name', $array)`, `set()`, `has()`, `forget()` |

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

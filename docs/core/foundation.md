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

50+ inline SVG icons with no external dependencies.

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

`check`, `x`, `pencil`, `trash`, `eye`, `plus`, `minus`, `search`, `filter`, `sort-asc`, `sort-desc`, `chevron-up`, `chevron-down`, `chevron-left`, `chevron-right`, `arrow-up`, `arrow-down`, `info`, `warning`, `exclamation`, `question`, `user`, `users`, `mail`, `phone`, `calendar`, `clock`, `document`, `folder`, `upload`, `download`, `link`, `external-link`, `copy`, `refresh`, `settings`, `menu`, `dots-horizontal`, `dots-vertical`, and more.

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

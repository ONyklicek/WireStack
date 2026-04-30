# Foundation

The Foundation module is the core of `wire-core`. It provides shared traits, base classes, and utilities used by all other modules and packages.

## Concerns (Traits)

### Component Traits

| Trait | Description |
|-------|-------------|
| `HasColor` | Tailwind CSS color class management |
| `HasIcons` | SVG icon rendering with built-in icon set |
| `HasVisibility` | Conditional visibility (`hidden()`, `visible()`) and permissions |
| `HasDynamicProperties` | Closure-based dynamic properties (resolve per record) |
| `HasKeyboardShortcut` | Keyboard shortcut support with Alpine.js |
| `HasLifecycle` | Before/after hooks with halt support |
| `HasLoadingState` | Loading indicators, debounce, timeout |
| `HasModal` | Modal/confirmation dialog configuration |
| `HasButtonStyles` | Button CSS class generation (size, color, variant) |

### Usage

Traits are composed into Action classes, Column classes, and Field classes:

```php
class Action extends BaseAction
{
    use HasColor;
    use HasIcons;
    use HasVisibility;
    use HasDynamicProperties;
    // ...
}
```

## Base Classes

| Class | Description |
|-------|-------------|
| `Component` | Abstract base for all components (make, name, key) |
| `ViewComponent` | Component that renders a Blade view |
| `LayoutComponent` | Component that contains child components (schema) |

## Icons

Built-in icon set with 50+ SVG icons rendered inline. No external dependencies.

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

// In Blade
<x-wire::icon name="check" class="w-5 h-5" />
<x-wire::icon name="trash" class="w-4 h-4 text-red-500" />
```

Available icons: `check`, `x`, `pencil`, `trash`, `eye`, `plus`, `minus`, `search`, `filter`, `sort-asc`, `sort-desc`, `chevron-up`, `chevron-down`, `chevron-left`, `chevron-right`, `arrow-up`, `arrow-down`, `info`, `warning`, `exclamation`, `question`, `user`, `users`, `mail`, `phone`, `calendar`, `clock`, `document`, `folder`, `upload`, `download`, `link`, `external-link`, `copy`, `refresh`, `settings`, `menu`, `dots-horizontal`, `dots-vertical`, and more.

## Colors

Tailwind CSS color abstraction:

```php
use NyonCode\WireCore\Foundation\Colors\Color;

// Used in actions, columns, badges
Action::make('delete')->color('danger');
BadgeColumn::make('status')->colors([
    'active' => 'success',
    'pending' => 'warning',
    'inactive' => 'danger',
]);
```

Supported color names: `primary`, `secondary`, `success`, `danger`, `warning`, `info`, `gray`.

## Support Utilities

| Class | Description |
|-------|-------------|
| `EvaluatesClosures` | Trait for evaluating Closure-or-value properties |
| `ArrayDotHelper` | Dot notation access for nested arrays |

## Blade Components

Foundation provides base Blade components under the `wire::` namespace:

```blade
<x-wire::icon name="check" />
<x-wire::badge color="success">Active</x-wire::badge>
<x-wire::button color="primary" size="sm">Save</x-wire::button>
<x-wire::dropdown>
    <x-slot:trigger>Options</x-slot:trigger>
    <x-wire::dropdown.item>Edit</x-wire::dropdown.item>
    <x-wire::dropdown.item>Delete</x-wire::dropdown.item>
</x-wire::dropdown>
```

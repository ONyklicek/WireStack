# Wire Core

Shared foundation for the [Wire ecosystem](https://github.com/NyonCode/wire) – traits, actions, modals, notifications, icons, and colors.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3.x

## Installation

Wire Core is typically installed as a dependency of `wire-forms` or `wire-table`. You don't usually install it directly:

```bash
composer require nyoncode/wire-core
```

### Tailwind CSS

Wire Core's Blade components (icons, badges, buttons, dropdowns, modals, notifications) use Tailwind CSS classes. Add the package views to your Tailwind content configuration:

**Tailwind 3** (`tailwind.config.js`):

```js
export default {
    content: [
        // ... your app paths
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    ],
}
```

**Tailwind 4** (`resources/css/app.css`):

```css
@source "../../vendor/nyoncode/wire-core/resources/views";
```

### Alpine.js

Wire Core uses inline Alpine.js directives (included via Livewire 3). No separate Alpine installation needed.

## What's Included

### Concerns (Traits)
Shared traits used by Actions, Columns, Fields, and other components:

- `HasColor` – Tailwind CSS color class management
- `HasIcons` – SVG icon rendering with 50+ built-in icons
- `HasVisibility` – Conditional visibility, permissions, disabled state
- `HasDynamicProperties` – Closure-based dynamic properties (label, color, icon per record)
- `HasKeyboardShortcut` – Keyboard shortcut support with Alpine.js integration
- `HasLifecycle` – Before/after hooks with halt support
- `HasLoadingState` – Loading indicators, debounce, timeout
- `HasModal` – Modal/confirmation dialog configuration
- `HasButtonStyles` – Button CSS class generation

### Actions
Complete action system for row, bulk, and header actions:

- `Action`, `BulkAction`, `HeaderAction` – Action types
- `ActionGroup` – Dropdown grouping
- `ActionHalt` – Pipeline halt with modal
- `DeleteAction`, `EditAction`, `ViewAction` – Pre-built actions
- `ModalStep`, `ModalFooterAction` – Multi-step wizard support

### Notifications
Pluggable notification system with three built-in drivers:

- `SessionDriver` – Laravel session flash (default)
- `LivewireEventDriver` – Livewire browser events
- `FlasherDriver` – PHP Flasher integration

### Configuration
Publish the config file:

```bash
php artisan vendor:publish --tag=wire-core-config
```

## License

MIT

# Wire Core

Shared foundation for the [Wire ecosystem](https://github.com/NyonCode/wire) - traits, actions, modals, notifications, widgets, audit logging, icons, and colors.

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

### Modals
Reusable modal primitives for actions and custom UI:

- Confirmation dialogs
- Slide-overs
- Multi-step wizards

### Widgets
Dashboard building blocks:

- Stats overview widgets
- Chart widgets
- Table widgets
- Custom Blade-backed widgets

### Plugins
Application and package extension points:

- Plugin lifecycle with `register()` and `boot()`
- Table and action macros
- Hook callbacks
- Column and filter type registries
- Query pipe registry

### Audit Log
Optional audit logging for Eloquent model changes and table-related events:

- `HasAuditable` model trait
- `AuditEntry` model and `audit_logs` migration
- `AuditTrailAction` row action for tables
- `AuditLogger::withoutAuditing()` for imports and maintenance jobs

### Configuration
Publish the config file:

```bash
php artisan vendor:publish --tag=wire-core-config
```

Publish audit migrations when you use the audit log:

```bash
php artisan vendor:publish --tag=wire-core-migrations
php artisan migrate
```

## Documentation

| Document | Description |
|----------|-------------|
| [Core Foundation](../../docs/core/foundation.md) | Shared traits, icons, colors, and Blade helpers |
| [Actions](../../docs/core/actions.md) | Row, bulk, header actions, and action groups |
| [Notifications](../../docs/core/notifications.md) | Notification value objects and drivers |
| [Modals](../../docs/core/modals.md) | Confirmations, slide-overs, and wizards |
| [Widgets](../../docs/core/widgets.md) | Dashboard widgets |
| [Plugins](../../docs/core/plugins.md) | App and package extension points |
| [Audit Log](../../docs/core/audit.md) | Audit setup and usage |
| [Configuration](../../docs/configuration.md) | Config files and environment variables |

## License

MIT

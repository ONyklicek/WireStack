# Core Package

Owner package: `packages/core`

## What It Owns

`wire-core` is the shared base for the rest of the repo. It owns:

- foundation traits and UI helpers
- actions
- modals
- notifications
- widgets
- the internal core runtime for metadata, state, validation, actions, plugins, and query support

## First Files To Read

- `packages/core/src/WireCoreServiceProvider.php`
- `packages/core/src/Foundation/`
- `packages/core/src/Actions/`
- `packages/core/src/Modals/`
- `packages/core/src/Notifications/`
- `packages/core/src/Widgets/`
- `packages/core/src/Core/`

If the task is about package extension points:

- `packages/core/src/Core/Plugin/PluginManager.php`

## Provider Responsibilities

`WireCoreServiceProvider` does most runtime bootstrapping:

- registers Blade namespaces:
  `wire`, `wire-actions`, `wire-modals`, `wire-notifications`
- registers core singletons:
  `ValidationPipeline`, `ActionRegistry`, `MetadataRegistry`, `PluginManager`
- binds `ActionPipeline`
- boots plugins from `wire-core.plugins`

If the task changes shared rendering or registration behavior, start here.

## Internal Layout

### `Foundation/`

Lowest-level reusable code. Other modules depend on this. It contains:

- shared traits
- base component/view classes
- contracts
- icon system
- color abstraction
- support helpers

### `Actions/`

Action objects, concerns, and Blade components for:

- row actions
- bulk actions
- header actions
- action groups
- wizard/footer actions

Shared base:

- `packages/core/src/Actions/BaseAction.php`

### `Modals/`

Core modal objects and modal Blade views:

- `Modal`
- `ConfirmationDialog`
- `SlideOver`
- `Wizard`

Views live under:

- `packages/core/resources/views/modals/`

### `Notifications/`

Notification value objects and drivers.

Relevant files:

- `packages/core/src/Notifications/Notification.php`
- `packages/core/src/Notifications/NotificationManager.php`
- `packages/core/src/Notifications/Drivers/`

Driver choice is configured through `wire-core.notifications.default`.

### `Widgets/`

Widget abstractions and shared dashboard-style pieces.

### `Core/`

Internal runtime and engine code. Read only the submodule you need:

- metadata:
  `Metadata/`
- state:
  `State/`
- validation:
  `Validation/`
- actions runtime:
  `Actions/`
- plugins:
  `Plugin/`
- query/runtime engine details:
  `Query/`, `Hydration/`, `Relations/`, `Components/`

For the full engine rationale, use:

- `architecture/core/unified-engine.md`

## Module Rules

- `Foundation` should stay dependency-light.
- `Actions`, `Modals`, and `Notifications` should not grow hard dependencies on each other.
- Cross-module coordination should go through the container or explicit runtime abstractions, not ad hoc imports.

## Typical Changes

- action label/icon/modal behavior:
  `Actions/` plus `resources/views/`
- shared icon/color behavior:
  `Foundation/Icons/`, `Foundation/Colors/`, related traits
- modal rendering:
  `Modals/` plus `resources/views/modals/`
- notification delivery:
  `Notifications/Drivers/` and `NotificationManager`
- plugin boot/wiring:
  `Core/Plugin/` and `WireCoreServiceProvider`

## Tests To Run

Start with:

- `composer test:core`

Then add downstream tests if the change is consumed outside core:

- actions/forms integration:
  `composer test:forms`
- shared runtime used by tables:
  `composer test:table`
- plugin/runtime surface:
  `composer test:sortable`
- broad wiring/state behavior:
  `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`

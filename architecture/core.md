# Core Package

Owner package: `packages/core`
Root namespace: `NyonCode\WireCore`

## What It Owns

`wire-core` is the shared base for the rest of the repo (`wire-sortable -> wire-table -> wire-forms -> wire-core`). Everything downstream builds on it. It owns:

- **Foundation** — lowest-level reusable traits, contracts, icon/color systems, Blade view components, closure evaluation helpers
- **Actions** — row/bulk/header actions, action groups, modal-backed actions, lifecycle hooks
- **Modals** — modal, confirmation dialog, slide-over, and wizard value objects + views
- **Notifications** — notification value objects + pluggable delivery drivers
- **Widgets** — stats, charts, table widgets, custom dashboard widgets
- **Audit** — model audit trail logging via event subscriber
- **Core/** (the Unified Engine) — metadata, capabilities, relation AST, query planning/execution, state, hydration, validation, action pipeline, events, plugins

> The `Core/` namespace is large enough to have its own deep doc. For metadata, query planning/execution, state, hydration, validation, the action pipeline, capabilities, the relation AST, and events, read **[`architecture/core/unified-engine.md`](core/unified-engine.md)** and **[ADR 0013](decisions/0013-unified-data-ui-engine.md)**. This file documents the *outer* package surface (Foundation, Actions, Modals, Notifications, Widgets, Audit, provider wiring) and routes into the engine doc where needed.

## First Files To Read

- `packages/core/src/WireCoreServiceProvider.php` — all runtime wiring
- `packages/core/config/wire-core.php` — config surface
- `packages/core/src/Foundation/` — base traits, contracts, view components
- `packages/core/src/Actions/BaseAction.php` — action base + concern composition
- `packages/core/src/Modals/` — modal value objects
- `packages/core/src/Notifications/` — notifications + drivers
- `packages/core/src/Widgets/` — widgets
- `packages/core/src/Core/` — the Unified Engine (see engine doc)

If the task is about package extension points:

- `packages/core/src/Core/Plugin/PluginManager.php` (see [plugins doc](core/plugins.md) if present)

---

## Provider Responsibilities

`WireCoreServiceProvider` extends `PackageServiceProvider` (from `nyon-code/laravel-package-toolkit`). It splits work into `register()` and `boot()`:

```text
register()                         boot()
├─ registerFoundation()            ├─ bootFoundation()   → Blade namespace "wire"
│   └─ IconManager (singleton)     ├─ bootActions()      → Blade namespace "wire-actions" + aliases
├─ registerCore()                  ├─ bootNotifications()→ Blade namespace "wire-notifications"
│   ├─ ValidationPipeline (singleton)   ├─ bootModals()  → Blade namespace "wire-modals" + aliases
│   ├─ ActionRegistry    (singleton)    └─ bootPlugins() → PluginManager::boot()
│   ├─ MetadataRegistry  (singleton)
│   └─ ActionPipeline    (bind / transient)
├─ registerNotifications()
│   ├─ NotificationDriver (singleton, resolved from config)
│   └─ NotificationManager (singleton)
└─ registerPlugins()
    └─ PluginManager (singleton, populated afterResolving from wire-core.plugins)
```

### Container bindings — singleton vs transient

| Binding | Lifetime | Why |
|---------|----------|-----|
| `IconManager` | singleton | icon registry is process-global |
| `ValidationPipeline` | singleton | stateless, reused everywhere |
| `ActionRegistry` | singleton | shared action lookup |
| `MetadataRegistry` | singleton | caches model introspection across requests |
| `ActionPipeline` | **transient** (`bind`) | each action execution gets a fresh pipeline |
| `NotificationDriver` | singleton | resolved once from `wire-core.notifications.default` |
| `NotificationManager` | singleton | facade-style entry point |
| `PluginManager` | singleton | one plugin graph per app |

### Blade component namespaces registered

| Namespace | Source namespace | Example tag |
|-----------|------------------|-------------|
| `wire` | `Foundation\View` | `<x-wire::icon />`, `<x-wire::badge />`, `<x-wire::button />` |
| `wire-actions` | `Actions\View` | `<x-wire-actions::button />`, `<x-wire-actions::group />`, `<x-wire-actions::bulk-button />` |
| `wire-modals` | `Modals\View` | `<x-wire-modals::modal />`, `<x-wire-modals::confirmation />`, `<x-wire-modals::slide-over />` |
| `wire-notifications` | `Notifications\View` | `<x-wire-notifications::toast-container />` |

Plus explicit aliases for cleaner names: `wire-actions::button/group/bulk-button`, `wire-modals::modal/confirmation/slide-over`, and the universal `wire::modal`.

### Plugin boot order

Plugins are registered lazily via `afterResolving(PluginManager::class, ...)`: each class in `wire-core.plugins` is validated as a `Plugin` subclass, made through the container, and `register()`-ed. `bootPlugins()` then calls `PluginManager::boot()` during the provider boot phase.

If the task changes shared rendering, container wiring, or registration behavior, **start here**.

---

## Config Surface (`config/wire-core.php`)

| Key | Default | Purpose |
|-----|---------|---------|
| `notifications.default` | `env('WIRE_NOTIFICATIONS_DRIVER', 'session')` | driver: `session` / `livewire` / `flasher` / `null` |
| `icons.default_set` | `'default'` | active icon set name |
| `icons.sets` | `['default' => DefaultIconSet::class]` | registered icon sets |
| `colors.palette` | `[]` | custom color extensions |
| `plugins` | `[]` | plugin class list booted at startup |
| `modals.default_width` | `'md'` | default modal width |
| `modals.slide_over_width` | `'md'` | default slide-over width |
| `modals.close_on_click_away` | `true` | global modal dismiss behavior |
| `modals.close_on_escape` | `true` | global modal escape behavior |
| `audit.enabled` | `env('WIRE_AUDIT_ENABLED', true)` | toggle the audit subsystem |
| `audit.model` | `AuditEntry::class` | audit log Eloquent model |
| `audit.user_model` | `env('WIRE_AUDIT_USER_MODEL', 'App\Models\User')` | actor model |
| `audit.events` | `null` | which events to record (null = all) |
| `audit.exclude_columns` | `[...]` | columns never logged (e.g. timestamps) |
| `audit.retention_days` | `null` | prune horizon (null = keep forever) |

---

## Internal Layout

### `Foundation/`

Lowest-level reusable code; everything else depends on it. **Keep it dependency-light.**

- **`Concerns/`** — composable traits mixed into components/fields/columns. The shared vocabulary:
  `HasName`, `HasLabel`, `HasId`, `HasColor`, `HasIcon`, `HasSize`, `HasState`, `HasDefault`,
  `HasPlaceholder`, `HasHelperText`, `HasHint`, `HasTooltip`, `HasPrefixAndSuffix`,
  `HasColumnSpan`, `HasExtraAttributes`, `HasVisibility`, `HasAuthorization`, `HasDebounce`,
  `HasLivewire`, `CanBeLive`, `CanBeReadOnly`, `BelongsToComponent`.
  These are the trait building blocks reused by both wire-forms fields and wire-table columns.
- **`Contracts/`** — `HasIcon`, `HasLabel`, `HasVisibility` interfaces.
- **`Icons/`** — `IconManager` (singleton registry), `Icon`, `IconSet` (interface), `DefaultIconSet`.
- **`Colors/`** — `Color` enum.
- **`View/`** — Blade view-class components: `Badge`, `Button`, `Dropdown`, `Icon` (the `wire` namespace).
- **`Components/`** — base classes `Component`, `LayoutComponent`, `ViewComponent`.
- **`Support/`** — `ArrayDotHelper`, `EvaluatesClosures`.

#### Icon system

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

$icons = app(IconManager::class);
$icons->registerIconSet($customSet);          // add a full IconSet
$icons->registerIcons(['star' => '<svg…>']);   // add ad-hoc icons
$icons->has('star');                            // bool
$icons->getPath('star');                        // resolve registered svg/path
$icons->render('star', size: 'w-5 h-5', class: 'text-primary'); // inline svg string
```

The default set is selected by `icons.default_set`; add sets under `icons.sets`.

#### Color enum

```php
use NyonCode\WireCore\Foundation\Colors\Color;

Color::Primary  // 'primary'
Color::Success  // 'success'
Color::Danger   // 'danger'
Color::Warning  // 'warning'
Color::Info     // 'info'
Color::Gray     // 'gray'
Color::Purple   // 'purple'
Color::Pink     // 'pink'
```

Methods that accept color almost always accept `string|Color|null` (and often `Closure`), so callers can pass either the enum or a raw palette key. Extend via `colors.palette`.

#### Closure evaluation (`EvaluatesClosures`)

```php
protected function evaluate(mixed $value, array $namedArgs = []): mixed
```

The mechanism behind "dynamic properties": any setter that accepts `string|Closure` stores the closure and resolves it at render with contextual named arguments (e.g. `$record`, `$state`). This is what powers `HasDynamicProperties` on actions.

---

### `Actions/`

Action objects, concerns, and Blade components.

**Class hierarchy**

- `BaseAction` — root; composes the concern stack below
- `Action` — standard row action; `DeleteAction`, `EditAction`, `ViewAction` extend it
- `BulkAction` extends `BaseAction` — operates on a collection; `DeleteBulkAction`, `ForceDeleteBulkAction`, `RestoreBulkAction`
- `HeaderAction` extends `BaseAction` — table-header level
- `ActionGroup` (implements `Htmlable`) — `ActionGroup::make([...])` groups actions into a dropdown
- `ModalFooterAction`, `ModalStep` — modal/wizard footer + step actions
- `ActionHalt` — sentinel returned by `halt()` to stop a pipeline

Concrete actions expose `static make(string $name = 'delete'|'edit'|'view')`.

**Concern stack on `BaseAction`** (`Actions/Concerns/`):

| Concern | Key API |
|---------|---------|
| `HasDynamicProperties` | `label()`, `color()`, `tooltip()`, `icon($icon, $position)`, `size()`, `extraAttributes()` — all `string\|Closure`, resolved with context |
| `HasVisibility` | `visible()`, `hidden()`, `disabled()` (`bool\|Closure`), `isHidden()`, `isDisabled()`, `canExecute()` |
| `HasLifecycle` | `before()`, `after()`, `successNotification()`, `failureNotification()`, `successRedirect()`, `halt()`, `send*Notification()` |
| `HasModal` | `requiresConfirmation()`, `modalHeading/Description/Icon/Width()`, `form()`, `formValidation()`, `slideOver()`, `fillFormUsing()` — see below |
| `HasLoadingState` | `loadingIndicator()`, `debounce(ms)`, `timeout(s)`, `getWireClickModifiers()`, `getLoadingStateData()` |
| `HasKeyboardShortcut` | `keyboardShortcut('mod+s')`, `getAlpineKeydownExpression()`, `shortcutUsesMod()` |
| `HasColor`, `HasIcons` | color resolution + svg rendering helpers |

**Action lifecycle.** An action's `before()`/`after()` hooks, notifications, and redirect feed the engine's **Action Pipeline** (`Core/Actions/`). The pipeline stages (`BeforeCallbacksStage → ActionExecutionStage → AfterCallbacksStage → NotificationStage → RedirectStage`) consume what these concerns configure. Returning `halt()` (an `ActionHalt`) from `before()` stops execution. See the [Action Pipeline section of the engine doc](core/unified-engine.md#action-pipeline) for `ActionContext`/`ActionResult`/`ActionPipeline`.

**Modal-backed actions** (`HasModal`). An action becomes modal-driven when it calls `requiresConfirmation()`, `form()`, or `slideOver()`:

```php
EditAction::make()
    ->modalHeading('Edit user')
    ->modalDescription('Update the user profile')
    ->modalIcon('pencil', Color::Primary)
    ->form([...])                       // array | Form | Closure
    ->formValidation(['name' => 'required'])
    ->validationMessages([...])
    ->fillFormUsing(fn ($record) => $record->only(['name', 'email']))
    ->modalWidth('lg')
    ->slideOver()                       // render as slide-over instead of centered modal
    ->closeModalOnClickAway(false)
    ->before(fn ($record) => /* … */)
    ->after(fn ($record) => /* … */)
    ->successNotification('Saved');
```

**View components** (`Actions/View/`): `ButtonComponent`, `BulkButtonComponent`, `GroupComponent` — the `wire-actions` Blade namespace.

---

### `Modals/`

Modal value objects describe a modal's chrome; views render it. All expose a fluent builder and `toArray()` for serialization into Livewire state.

| Class | Builder highlights |
|-------|--------------------|
| `Modal` | `icon($icon, $color)`, `color()`, `fullScreenOnMobile()`, `mobileWidth()` |
| `ConfirmationDialog` | `icon()`, `danger()`, `informative()`, `getSubmitLabel()`, `getCancelLabel()` |
| `SlideOver` | `icon()`, `color()`, `position('right'\|'left')`, `mobileOnly()` |
| `Wizard` | `steps([...])`, `skippable()`, `getTotalSteps()`, `getStepsConfig($context)` |

Shared concerns: `Modals/Concerns/HasModalProperties.php`, `Modals/Concerns/HasFooterActions.php`.

Views live under `packages/core/resources/views/modals/`. View-class components (`Modals/View/`): `ModalComponent`, `ConfirmationComponent`, `SlideOverComponent` (the `wire-modals` namespace, plus the universal `wire::modal` alias).

Global defaults (`default_width`, `slide_over_width`, `close_on_click_away`, `close_on_escape`) come from `wire-core.modals`.

---

### `Notifications/`

Notification value objects and pluggable delivery. **Full content doc: [`architecture/core/notifications.md`](core/notifications.md).** Summary:

- **`Notification`** — immutable value object; factories `make/success/error/warning/info`, fluent `title/duration/icon/position/extra`, `toArray()` (strips nulls). Canonical types: `success`, `error`, `warning`, `info` (note `error`, not `danger`).
- **`NotificationManager`** — all-static facade; `success/error/warning/info/send` plus `setDefaultDriver/getDefaultDriver/resolve/reset`. Driver resolution priority: **explicit > global default > built-in `SessionDriver`**.
- **`InteractsWithNotifications`** trait — for Livewire components: `notify()`, `notifySuccess/Error/Warning/Info()`, `setNotificationDriver()`.
- **Drivers** (`Notifications/Drivers/`), selected by `wire-core.notifications.default`:

| Config value | Driver | Delivery |
|--------------|--------|----------|
| `session` *(default)* | `SessionDriver` | session flash **+** Livewire event (`type`+`message` only) |
| `livewire` | `LivewireEventDriver` | Livewire browser event with full `toArray()` payload |
| `flasher` | `FlasherDriver` | integrates `php-flasher` (graceful session fallback) |
| `null` | `NullDriver` | no-op (tests / disabling) |

All implement `Contracts\NotificationDriver::send(Notification $notification, mixed $livewireComponent = null): void`. Write a custom driver by implementing that interface and registering it via `setDefaultDriver()` or the provider.

**Frontend.** Toasts render via `<x-wire-notifications::toast-container />` (`Notifications/View/ToastContainer.php`), which listens for the driver's Livewire event (default `table-notification`).

> ⚠️ Two known gotchas (detailed in the content doc): the static `NotificationManager` default is **independent of config** (defaults to `SessionDriver` unless bridged), and `SessionDriver` only transmits `type`+`message` over the live event — richer fields need `LivewireEventDriver`.

> `TableNotification` and `TableNotificationManager` are **deprecated `class_alias`es** for `Notification` / `NotificationManager` (removal in v2.0), not separate table-scoped classes.

---

### `Widgets/`

Dashboard-style pieces. `Widget` is the base (`heading()`, `description()`, `lazy()`, `render(): View`, `toHtml()`).

| Widget | Purpose | Key API |
|--------|---------|---------|
| `StatsOverviewWidget` | grid of stat cards | `stats([Stat, …])`, `columns(int)` |
| `Stat` | single metric card | `Stat::make($label, $value)`, `description()`, `descriptionIcon()`, `color()`, `icon()`, `chart([…])` (sparkline) |
| `ChartWidget` | full chart | `type()`, `datasets()` / `labels()` (`array\|Closure`), `filter($options, $default)`, `activeFilter()` |
| `TableWidget` | embed a wire-table | wraps a table component |
| `CustomWidget` | arbitrary view | render any Blade view as a widget |

Concerns: `Widgets/Concerns/HasPolling.php` (live refresh), `Widgets/Concerns/WithWidgets.php` (host trait). Contract: `Widgets/Contracts/HasWidgets.php`.

```php
StatsOverviewWidget::make()
    ->columns(3)
    ->stats([
        Stat::make('Users', '1,024')->description('+12%')->descriptionIcon('arrow-up')->color(Color::Success)->chart([1,3,2,5,4]),
        Stat::make('Revenue', '$8.4k')->color(Color::Primary),
    ]);
```

---

### `Audit/`

Model audit trail (gated by `wire-core.audit.enabled`).

- `AuditLogger` — writes entries to `audit.model` (`AuditEntry`)
- `AuditEventSubscriber` — listens for auditable events and logs them
- `Events/` — `RecordCreated`, `RecordUpdated`, `RecordDeleted`, `BulkActionExecuted`, `InlineCellUpdated`
- `Concerns/HasAuditable.php` — trait for models to opt into auditing
- `Contracts/AuditableEvent.php` — marker for loggable events
- `Actions/AuditTrailAction.php` — surface the trail in the UI

`exclude_columns` and `retention_days` tune what is stored and for how long. For full behavior see `architecture/audit.md`.

---

### `Core/` (Unified Engine)

Internal runtime shared by wire-table and wire-forms. **Read only the submodule you need**, and prefer the dedicated engine doc over reading source first:

| Submodule | Owns | Engine-doc anchor |
|-----------|------|-------------------|
| `Metadata/` | model introspection + cache | [Metadata System](core/unified-engine.md#metadata-system) |
| `Capabilities/` | what a component can do | [Capability System](core/unified-engine.md#capability-system) |
| `Relations/` | dot-path AST + relation graph | [Relation AST](core/unified-engine.md#relation-ast) |
| `Query/` | planner, executor, pipes, strategies | [Query Planning](core/unified-engine.md#query-planning) / [Execution](core/unified-engine.md#query-execution) |
| `State/` | StateContainer + dirty tracking | [State Engine](core/unified-engine.md#state-engine) |
| `Hydration/` | model ⇄ state arrays | [Hydration System](core/unified-engine.md#hydration-system) |
| `Validation/` | reusable validation pipeline | [Validation System](core/unified-engine.md#validation-system) |
| `Actions/` | action pipeline + stages + registry | [Action Pipeline](core/unified-engine.md#action-pipeline) |
| `Events/` | lifecycle events | [Events](core/unified-engine.md#events) |
| `Components/` | `DataComponent` base | [DataComponent](core/unified-engine.md#datacomponent) |
| `Plugin/` | plugin manager + hooks | [plugins doc](core/plugins.md) |
| `Support/` | `DriverDetector`, `SqlSafety`, `Deprecation` | [Support Utilities](core/unified-engine.md#support-utilities) |

For the full engine rationale, read **[`architecture/core/unified-engine.md`](core/unified-engine.md)**.

---

## Module Rules

- `Foundation` stays dependency-light — it is the bottom of the graph.
- `Actions`, `Modals`, and `Notifications` should not grow hard dependencies on each other; coordinate through the container or explicit runtime abstractions, not ad-hoc imports.
- New shared trait vocabulary belongs in `Foundation/Concerns/`, not duplicated per package.
- Anything touching SQL identifiers must go through `Core/Support/SqlSafety` — never interpolate column/operator/direction strings directly.
- Dynamic (`string|Closure`) properties resolve through `EvaluatesClosures::evaluate()`; follow that pattern instead of inventing a second closure mechanism.

---

## Typical Changes

| Goal | Touch |
|------|-------|
| action label/icon/color/modal behavior | `Actions/Concerns/` + `Actions/View/` + `resources/views/` |
| new built-in action | new class in `Actions/` extending `Action`/`BulkAction`/`HeaderAction` |
| shared icon/color behavior | `Foundation/Icons/`, `Foundation/Colors/`, related concerns |
| modal rendering / chrome | `Modals/` + `resources/views/modals/` |
| notification delivery | `Notifications/Drivers/` + `NotificationManager` |
| new widget type | `Widgets/` + `Widgets/View/` |
| audit behavior | `Audit/` + `wire-core.audit.*` config |
| plugin boot/wiring | `Core/Plugin/` + `WireCoreServiceProvider::registerPlugins()` |
| query/state/validation engine | `Core/<submodule>/` — read the engine doc first |
| container wiring / Blade namespaces | `WireCoreServiceProvider` |

---

## Tests To Run

Start narrow:

```bash
composer test:core
```

Then add downstream tests when the change is consumed outside core:

| If the change affects… | Run |
|------------------------|-----|
| actions / forms integration | `composer test:forms` |
| shared runtime used by tables | `composer test:table` |
| plugin/runtime surface | `composer test:sortable` |
| state, rendering, macros, plugins, runtime wiring | `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"` |

Lint/static analysis before handing off:

```bash
composer lint
composer analyse
```

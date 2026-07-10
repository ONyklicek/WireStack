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
| `icons.sets` | `['default' => DefaultIconSet::class]` | base set unprefixed; other sets keyed by required prefix (`prefix:name`) |
| `icons.paths` | `[]` | SVG folders to auto-register; string key = name prefix |
| `icons.warn_missing` | `false` | log a warning when an unknown icon is rendered |
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
- **`Icons/`** — `IconManager` (singleton registry), `Icon`, `IconSet` (interface), `ProvidesIconMetadata` (optional capability), `ResolvedIcon` (value object), `DefaultIconSet`. `DefaultIconSet` ships the complete Heroicons 2.2.0 solid set (324 icons, 20x20) plus Wire-friendly aliases; paths come from the generated `resources/icons/heroicons-solid.php`, loaded lazily. The `Icon` enum maps friendly names/aliases to canonical Heroicons names. Multiple sets can be registered and used at once: the bundled Heroicons set is the **unprefixed base** (`pencil`, `user`), and every additional set is registered under a **required prefix** and addressed as `prefix:name` (`lucide:home`) — registering a non-default set without a prefix throws, so resolution is deterministic and sets never collide. Sets implementing `ProvidesIconMetadata` carry their own viewBox + fill/stroke attributes (via `ResolvedIcon`), so stroke-based or non-20x20 sets (Lucide, Feather, Heroicons outline) render correctly next to the default solid set. Custom icons (`registerIcons`/`registerIconsFromDirectory`) are flat bare names that take priority over the default set. `IconManager::render()` accepts a `label` for accessibility (`role="img"`), else emits `aria-hidden`. Sets implementing only `IconSet` still work — their `getPath()` is wrapped in the default 20x20 fill format. `setDefaultIconSet()` swaps the unprefixed base.
- **`Colors/`** — `Color` enum.
- **`View/`** — Blade view-class components: `Badge`, `Button`, `Dropdown`, `Icon` (the `wire` namespace).
- **`Components/`** — base classes `Component`, `LayoutComponent`, `ViewComponent`.
- **`Support/`** — `ArrayDotHelper`, `EvaluatesClosures`.

#### Icon system

```php
use NyonCode\WireCore\Foundation\Icons\IconManager;

$icons = app(IconManager::class);
$icons->registerIconSet($customSet);            // add a full IconSet
$icons->registerIcons(['star' => '<svg…>']);    // add ad-hoc icons (full <svg> normalized)
$icons->registerIconsFromDirectory($dir, 'brand'); // load *.svg from a folder ("brand-<file>")
$icons->has('star');                            // bool
$icons->getPath('star');                        // resolve registered svg/path
$icons->render('star', size: 'w-5 h-5', class: 'text-primary'); // inline svg string
```

Config wiring (`WireCoreServiceProvider::registerFoundation`): `IconManager` is a
singleton seeded with `DefaultIconSet`, then it registers every set under
`icons.sets` (except `default`, the base) and auto-loads SVG files from each
directory in `icons.paths`. `registerIcons()` accepts a full `<svg>` or a bare
path fragment (outer `<svg>` stripped via `normalizeSvg`). Icons are wrapped in a
fixed `0 0 20 20` viewBox by `render()`.

To refresh the bundled Heroicons, regenerate `resources/icons/heroicons-solid.php`
from the official `heroicons` npm package (`20/solid` SVGs, keyed by file name) —
do not edit icon paths by hand.

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

**Action lifecycle.** An action's `before()`/`after()` hooks, notifications, and redirect feed the engine's **Action Pipeline** (`Core/Actions/`). The action callback runs at the pipeline terminal, wrapped by stages `BeforeCallbacksStage` (pre + halt), then `AfterCallbacksStage → NotificationStage → RedirectStage` post-processing the result. Returning `halt()` (an `ActionHalt`) from `before()` stops execution before the action; an `after()` hook can halt after it. See the [Action Pipeline section of the engine doc](core/unified-engine.md#action-pipeline) for `ActionContext`/`ActionResult`/`ActionPipeline`.

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

- **`Notification`** — immutable value object; factories `make/success/error/warning/info`, fluent `title/duration/icon/position/extra/persistent/action/actions`, `toArray()` (strips nulls). Canonical types: `success`, `error`, `warning`, `info` (note `error`, not `danger`). `persistent()` = sticky (`duration 0`); `action('Undo', 'event')` / `action(NotificationAction::make(...))` append toast buttons that dispatch a Livewire event on click.
- **`NotificationAction`** — immutable VO for a toast button (`make(label, event)`, `payload/color/keepOpen`, `toArray`). Click → `Livewire.dispatch(event, payload)`, host listens with `#[On(event)]`.
- **`NotificationManager`** — all-static facade; `success/error/warning/info/send` plus `setDefaultDriver/getDefaultDriver/resolve/reset`. Driver resolution priority: **explicit > global default > built-in `CurrentComponentDriver(SessionDriver)`**.
- **`InteractsWithNotifications`** trait — for Livewire components: `notify()`, `notifySuccess/Error/Warning/Info()`, `setNotificationDriver()`. Call-sites do **not** pass `$this` — the default driver resolves the active component.
- **Drivers** (`Notifications/Drivers/`), selected by `wire-core.notifications.default`:

| Config value | Driver | Delivery |
|--------------|--------|----------|
| *(built-in default)* | `CurrentComponentDriver` | decorator: resolves `Livewire::current()`, delegates to a wrapped driver (`SessionDriver`) |
| `session` | `SessionDriver` | session flash **+** Livewire event with the full `toArray()` payload |
| `livewire` | `LivewireEventDriver` | Livewire browser event with full `toArray()` payload |
| `flasher` | `FlasherDriver` | integrates `php-flasher` (graceful session fallback) |
| `null` | `NullDriver` | no-op (tests / disabling) |

All implement `Contracts\NotificationDriver::send(Notification $notification, mixed $livewireComponent = null): void`. Write a custom driver by implementing that interface and registering it via `setDefaultDriver()` or the provider.

**Frontend.** Toasts render via `<x-wire-notifications::toast-container />` (`Notifications/View/ToastContainer.php`), which listens for the driver's Livewire event (default `table-notification`). Container props: `position`, `duration`, `event-name`, `progress` (countdown bar, hover-pauses), `stack` (collapsible pile, fans out on hover), `max` (visible cap + "+N more"). Honors `prefers-reduced-motion` and exposes an `aria-live` region.

> ⚠️ Gotcha: the static `NotificationManager` default is **independent of config** — it defaults to `CurrentComponentDriver(SessionDriver)` unless bridged from the container binding. All event-dispatching drivers forward the full payload; `window.wireToast` binds to the **first** mounted container (dispatch explicit `CustomEvent`s for secondary containers).

> `TableNotification` and `TableNotificationManager` are **deprecated `class_alias`es** for `Notification` / `NotificationManager` (removal in v2.0), not separate table-scoped classes.

---

### `Widgets/`

Dashboard-style pieces. `Widget` is the base (`heading()`, `description()`, `lazy()`, `render(): View`, `toHtml()`).

| Widget | Purpose | Key API |
|--------|---------|---------|
| `StatsOverviewWidget` | grid of stat cards | `stats([Stat, …])`, `columns(int)` |
| `Stat` | single metric card | `Stat::make($label, $value)`, `description()`, `descriptionIcon()`, `color()`, `icon()`, `chart([…])` (sparkline) |
| `ChartWidget` | full chart (JS / Chart.js) | `type()` (line\|bar\|pie\|doughnut), `datasets()` / `labels()` (`array\|Closure`), `filter($options, $default)`, `activeFilter()` |
| `BarChartWidget` | pure-CSS bar chart (no JS) | `type()` (vertical\|horizontal), `variant()` (finance\|system\|default), `items([ChartItem, …])`, `showGrid()`, `showMenu()`, `maxValue()`, `height()`, `rounded()` |
| `ChartItem` | one bar in `BarChartWidget` | `ChartItem::make($label)`, `value()`, `formattedValue()`, `color()`, `percentage(0–100)`, `icon()` |
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

#### `BarChartWidget` (CSS bar chart)

A dependency-free bar chart rendered with Tailwind utilities only — distinct from
the JS `ChartWidget`. Three visual modes are selected from `type` + `variant`:

| `type` | `variant` | Partial | Look |
|--------|-----------|---------|------|
| `vertical` | `finance` | `vertical-finance` | value above, light max-height track, `MM / YYYY` caption below |
| `vertical` | `system` / `default` | `vertical-system` | icon + label + % above a 0–100% track, optional grid lines |
| `horizontal` | `system` / `default` | `horizontal-system` | label left, value right, progress track |

`type()` and `variant()` validate against `BarChartWidget::TYPES` / `::VARIANTS`
(invalid values throw `InvalidArgumentException`). Each bar's fill percentage is
resolved by `percentageFor(ChartItem)`: an explicit `percentage()` wins, else the
value is scaled against `maxValue()`, else auto-scaled against the largest item.
Colors are mapped through the canonical `HasColor::getGradientFillClasses()`
allow-list (literal chart hues: `blue` → `blue-500/600`, `green` →
`green-500/600`, `gray` → `slate-400/500`), with the matching accent text from
`HasColor::getFillTextClasses()` — owner-supplied color names can never inject
arbitrary classes. The
dynamic fill size is the only inline style, passed as a CSS variable
(`style="--value: 72%"`) consumed by Tailwind arbitrary values
(`h-[var(--value)]` / `w-[var(--value)]`).

```php
// Finance — vertical revenue bars
BarChartWidget::make()
    ->heading('Přehled tržeb')
    ->type('vertical')->variant('finance')
    ->items([
        ChartItem::make('01 / 2024')->value(125000)->formattedValue('125 000 Kč')->color('blue')->percentage(70),
        ChartItem::make('02 / 2024')->value(98500)->formattedValue('98 500 Kč')->color('green')->percentage(55),
    ]);

// System — vertical metrics with grid lines
BarChartWidget::make()
    ->heading('Přehled systému')
    ->type('vertical')->variant('system')->showGrid()->maxValue(100)
    ->items([
        ChartItem::make('CPU')->value(72)->formattedValue('72 %')->icon('cpu-chip')->color('blue'),
        ChartItem::make('RAM')->value(54)->formattedValue('54 %')->icon('circle-stack')->color('green'),
        ChartItem::make('Disk')->value(81)->formattedValue('81 %')->icon('server')->color('orange'),
        ChartItem::make('GPU')->value(36)->formattedValue('36 %')->icon('bolt')->color('purple'),
    ]);

// System — horizontal progress bars (same items, type 'horizontal')
BarChartWidget::make()->type('horizontal')->variant('system')->maxValue(100)->items([/* … */]);
```

Safe color keys: `blue`, `green`, `orange`, `purple`, `gray` (plus the shared
palette vocabulary). Views: `wire-core::widgets.bar-chart` dispatches to the three
partials under `widgets/bar-chart/`.

---

### `Infolists/`

Read-only, declarative display of a single record — the display counterpart of a
wire-forms `Form`. Lives in core (next to `Widgets/`) because it is a display
assembly that depends only on shared Foundation pieces, not on forms or table.

`Infolist` is the public API (`make()`, `record()`/`state()`, `schema()`,
`columns()`, `Htmlable`). It reuses the **canonical schema layout** in
`Foundation/Schema/` (`Section`, `Grid`, `Fieldset`) — the same classes wire-forms
layouts now subclass for backward compatibility (forms keeps its own Blade chrome;
the shared config — heading, columns, collapsible, aside — lives once in core).

| Entry | Purpose | Key API |
|-------|---------|---------|
| `Entry` | base (extends `ViewComponent`) | `record()`, `state(Closure)`, `formatStateUsing()`, `color()`, `getState()` (resolves from record by name via `data_get`, dot-paths supported) |
| `TextEntry` | text/number/money/date | `money()/numeric()/date()/dateTime()/since()` (via shared `FormatsState`), `badge()`, `copyable()`, `limit()`, `weight()`, `prose()`, `listWithLineBreaks()`, `bulleted()` |
| `IconEntry` | state → icon | `boolean()`, `icons([state => name])`, `colors([...])`, `trueIcon/falseIcon/trueColor/falseColor()` |
| `ImageEntry` | image/avatar | `disk()`, `imageSize()`, `circular()`, `stacked()`, `defaultImageUrl()` |
| `ColorEntry` | color swatch | `copyable()` |
| `KeyValueEntry` | array → key/value table | `keyLabel()`, `valueLabel()` |
| `RepeatableEntry` | nested schema per item | `schema([...])`, `columns()`, `contained()` |

Numeric/money/date formatting is owned by the canonical
`Foundation/Concerns/FormatsState` concern and **shared with table columns**
(`TextColumn` uses the same trait), so a value formats identically wherever it is
displayed. Host trait: `Infolists/Concerns/WithInfolists`. Views:
`wire-core::infolists.infolist` + `infolists/entries/*`; layout views under
`wire-core::schema.*`.

**Action integration.** `HasModal` exposes `infolist(array|Infolist|Closure)`
alongside `form()`. An action with an infolist opens a read-only modal bound to
the record — `doesRequireConfirmation()` is false and the modal renders only a
close button (no submit). Mirrors `form()`: `getInfolistInstance($context)`
resolves the closure/array and binds the record, `getModalConfig()` reports
`hasInfolist`. The table runtime resolves it lazily via
`WithTable::getActionModalInfolistInstance()` (stateless, so no eager caching like
forms), and the action-modal partial renders it in slide-over and centered
variants.

```php
ViewAction::make()->slideOver()->infolist([
    TextEntry::make('name')->weight('bold'),
    TextEntry::make('email')->copyable(),
]);
```

```php
Infolist::make()
    ->record($user)
    ->schema([
        Section::make('Profil')->icon('user')->columns(2)->schema([
            TextEntry::make('name')->weight('bold'),
            TextEntry::make('email')->icon('envelope')->copyable(),
            TextEntry::make('created_at')->dateTime()->since(),
            TextEntry::make('status')->badge()
                ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray),
            IconEntry::make('is_verified')->boolean(),
        ]),
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
- Anything touching SQL identifiers must go through `Core/Support/SqlSafety` — never interpolate column/operator/direction strings directly. It owns the keyword allow-lists (`normalizeDirection`/`normalizeNullsPosition`, `assertValidOperator`, `assertValidQualifiedColumn`) and is wired into the raw-SQL paths: `SortClause` (direction/NULLS), `ApplyFilters` (operator before `whereRaw`), and `ApplySorting` (column before a `NULLS orderByRaw`).
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

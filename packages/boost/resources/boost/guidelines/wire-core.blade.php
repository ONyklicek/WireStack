## wire-core

Shared foundation for wireStack. Key building blocks:

### Actions

Row, header and bulk actions are objects with a fluent API and lifecycle hooks:

    Action::make('approve')
        ->label('Approve')
        ->icon('check')
        ->color('success')
        ->requiresConfirmation()
        ->action(fn ($record) => $record->approve());

- Presets: `DeleteAction`, `EditAction`, `ViewAction`, `RestoreAction`, `ForceDeleteAction`, plus bulk presets (`DeleteBulkAction`, `RestoreBulkAction`, `ForceDeleteBulkAction`). They confirm and label themselves; the host supplies `->action()`.
- `->url()` takes a **static string or a per-record Closure**, and the two are not interchangeable on a
  record-less surface (a header action, the table's empty state): a string resolves with or without a
  record, a Closure needs one and stays unresolved — the action then renders as a plain button, not a
  link. `Action::render()` / `getUrl()` therefore take an optional record, matching `RendersAsButton`.
- Actions can open modals via `->modal(...)` and multi-step wizards via `->steps([...])`.
- A wizard step's `->schema(fn (array $data) => [...])` Closure builds its fields from data entered in
  earlier steps; the bag is live even for header actions (no record), so later steps can branch on it.
- An action with a form (`->form([...])`) self-seeds each field from its `->default()` (or a
  type-correct blank), then layers record/context prefill on top with `->fillFormUsing(fn ($record) => [...])`
  (`$record` is `null` for header actions), which overrides the seed. Inside the form, reactive fields use `$get`/`$set` and
  `->afterStateUpdated()` against the live per-frame form-data bag (`mountedActions.{depth}.data`) — see the wire-forms guideline.
- Add extra footer buttons with `->modalFooterActions([ModalFooterAction::make('preview')->action(fn ($data, $set) => …)])`.
  The callback gets the live form `$data` and a `$set` writer; `->submitsForm()` validates first,
  `->closesModal()` closes after, `->position('before'|'after')` places it around Cancel/Submit.
- Modals **stack as a live frame array** (not one active + frozen parents): opening an action while a
  modal is open (from any callback with the host `$component` — a footer/field/infolist action calling
  `$component->mountAction(...)` / `$component->openActionModal(...)`) layers the new modal on top while
  **every parent stays a live, reactive form** behind it (dimmed + click-inert; only the top is interactive).
  A nested callback can write back into an ancestor via `$setParent(path, value)` / `$setFrame(depth, path, value)`,
  read `$parentData`, and receive `$arguments` (passed to `mountAction($name, [...])`) — so a sub-form can
  create-and-select into the form that opened it. Declare a nested action inline next to its opener with
  `->registerActions([Action::make('createCustomer')->…])` (resolved by name; no top-level registration needed).
  Closing the top (Escape/close/backdrop) pops just that level and resumes the parent with its data intact.
  Deep nesting works (capped at `Modals\ModalStack::MAX_DEPTH` as a runaway guard); only the top takes focus/Escape.
  `->requiresConfirmation()` asks before running (native `wire:confirm`, translated default message);
  `->confirm('Really reset?')` sets a custom message.
- Color, icon and visibility come from the shared `HasColor`, `HasIcons`, `HasVisibility` concerns.
- On actions, `->label()`, `->icon()`, `->color()`, `->tooltip()` and `->size()` each accept `string|Closure|null`,
  so they can be computed per row — the Closure receives the record: `->color(fn ($record) => $record->isPaid() ? 'success' : 'danger')`.
  This differs from table columns, where `->color()` is static and per-state colors use `->colorUsing()` / `->colors()`.
- `->action(fn ($record) => …)` runs the action; the callback receives the current record.
- Actions are not table-only. Any Livewire component can declare and run them (modal, form, wizard,
  confirmation, full lifecycle) with the `WithActions` trait plus a @verbatim`<x-wire-actions::modal-host />`@endverbatim component —
  see the wire-forms guideline. The engine is canonically owned by
  `Actions\Concerns\InteractsWithActions` (form-agnostic, here in wire-core) and the wire-forms
  form bridge; `WithTable` composes the same engine. Extend it rather than reimplementing action
  handling on a component.
- **A halt needs none of that.** `Actions\Concerns\InteractsWithHalt` is "stop, ask, continue" on its own:
  `$this->halt($halt, then: 'method', arguments: [...])` from any method, plus
  @verbatim`<x-wire-actions::halt-host />`@endverbatim (a host rendering the modal host already draws it).
  The resume is a **method name and scalars**, never a closure — a halt is answered on a later request than
  the one that raised it. Without wire-forms a halt has no fields; its declared rules are still checked.

### Modals

`Modal`, `ConfirmationDialog`, `SlideOver` and `Wizard` (modal **config** objects). Prefer attaching a
modal to an action over building bespoke modal state. Mobile presentation is per action and independent
of the modal type — it applies to a form, an infolist **and** a confirmation modal alike:
`->slideOverOnMobile()` renders it as a bottom-sheet that slides up from the bottom edge,
`->fullScreenOnMobile()` fills the viewport; both keep the centered dialog on desktop and scroll the body
inside the panel. These do **not** imply `->slideOver()` — that is a separate desktop right-hand panel;
combine `->slideOver()->slideOverOnMobile()` for a desktop slide-over that becomes a mobile bottom-sheet.

For a **standalone** modal in your own view use the component tag —
@verbatim`<x-wire-modals::modal wire:model="show" heading="…">…<x-slot:footer>…</x-slot:footer></x-wire-modals::modal>`@endverbatim
(also `::confirmation`, `::slide-over`). For **PHP-first** rendering without a `<x-*>` tag, echo the
Htmlable render object: @verbatim`{{ new NyonCode\WireCore\Modals\Html\Modal(heading: '…', wireModel: 'show', body: $form) }}`@endverbatim.
Both render the same shell. (Three families under `Modals\`: `Html\*` = Htmlable render objects,
`Modals\Modal` etc. = config, `Modals\View\*Component` = the Blade components — don't conflate.)

Either form works inside your own `@@island`. Livewire renders a targeted island without the shared
`$__livewire` a full render has, and these modals build their view from an explicit data array, so
`@@entangle` and `@@this` would otherwise fail with `Undefined variable $__livewire` — wire-core
restores that scope for the duration of an island render, so you do not have to. This applies to any
view you render from PHP inside an island, not only these.

### Mobile sheets

Floating panels (dropdowns, action-group menus, select/date/tag pickers, table filter & column-toggle
panels) and the modal variants above present as a **bottom sheet** below a breakpoint. Global defaults
live in `config('wire-core.mobile')`: `sheet` (bool, default `true`) and `breakpoint` (`sm`|`md`|`lg`,
default `sm`). Override per component with `->sheetOnMobile(true|false)` and `->mobileBreakpoint('md')`
(actions/fields/filters/`Table`/`ActionGroup`); the @verbatim`<x-wire::dropdown>`@endverbatim tag takes
`:sheet-on-mobile` / `:breakpoint`. Searchable selects default to floating. Sheets add safe-area padding,
a drag-to-dismiss grabber and a focus trap automatically — do not re-implement these.

**Native control on a phone.** Every surface with a browser counterpart (`Select`, `BelongsToSelect`,
`DateTimePicker`, `TimePicker`, `SelectFilter`, `TernaryFilter`) uses `Foundation\Concerns\HasNativeControl`:
`->native()` is the browser's element everywhere, `->nativeOnMobile()` only below the same mobile breakpoint
(global default `wire-core.mobile.native`, default `false`). Views branch on the one resolved value,
`getNativeControlMode()` (`NativeControlMode::Never|Always|Mobile`); Mobile renders both controls and
`MobileSheet::showBelow()` / `hideBelow()` pick one in CSS, with no sheet for that field. A surface whose
custom control carries something the native one cannot overrides `supportsNativeOnMobile()` (only remote
search and create/edit option stay custom). A clock step goes native as a `<select>` of the slots — beside a
native date on a datetime, joined by `wireNativeDateTime` — never as `<input type="time" step>`, which iOS ignores.
`DateTimePicker` also validates its bounds server-side (`DateWithinBounds`) — a native wheel ignores them.
**Touch-built control on a phone:** `Foundation\Concerns\HasNativeControl` — `->touchOnMobile()`, global
`wire-core.mobile.touch` — gives a picker a scroll-snap wheel and a select a bottom-sheet list, keeping remote
search, create-option and disabled days. Precedence is one rule: `native()` > `touchOnMobile()` >
`nativeOnMobile()`. The select list is `wire-core::partials.select-touch-sheet`, rendered by `select-control`
beside the floating panel on the same `wireSearchableSelect` state — never a second select component. Every select goes through
`wire-core::partials.select-control` — never hand-write a `<select>` or include the combobox beside it.

### Layouts

Canonical layout vocabulary shared by forms and infolists (`NyonCode\WireCore\Foundation\Schema\*`):
`Grid`, `Section`, `Fieldset`, `Flex` (side-by-side flexbox row, stacks below `->from('md')`, with
`->justify()/->align()/->gap()/->wrap()/->grow()`), `Tabs`+`Tab`, `Wizard`+`Step`, `Callout`
(`->heading()->color()/info()/success()/warning()/danger()->icon()->dismissible()`) and `EmptyState`
(`->icon()->heading()->description()->actions([])`). Column counts accept an int or a Filament-style
per-breakpoint map: `->columns(['default' => 1, 'md' => 2, 'lg' => 3])`. Prefer these over ad-hoc Blade
grids; the forms `Alert` field is the field-style alias of `Callout`.

@verbatim
Standalone Blade tags mirror them for plain views: `<x-wire::grid>`, `<x-wire::flex>`, `<x-wire::section>`,
`<x-wire::fieldset>`, `<x-wire::callout>`, `<x-wire::empty-state>`, and the Alpine-driven `<x-wire::tabs>` /
`<x-wire::wizard>` (with nested `<x-wire::tab>` / `<x-wire::step>`).
@endverbatim
The standalone tabs/wizard are client-side only (no per-step validation) — use action-modal wizards or
form schema for validated flows.

### Workflow / state machine

`WorkflowState::for(StatusEnum::class)->column('status')->allow($from, $to)->guard($to, fn)->after($to, fn)`.
A **seam, not an engine** (ADR 0018): it owns states, edges, guards and side effects, and delegates every
meaning — no process definitions, no approval modelling, no scheduler. Transitions save through the ordinary
path, so tenant scoping and audit come along without rewiring.

**Never add colour/label/icon here.** The status is an enum implementing `Enum\HasColor`/`HasLabel`/`HasIcon`
and `BadgeColumn` already renders it — a second map is a parallel vocabulary that drifts. (This is also why
`StatusColumn` was never built: `BadgeColumn` covers it.)

**Two refusals, deliberately different.** An illegal edge **throws** — silence leaves a record where the user
believes it moved on. A guard veto returns **false** — "not yet" is a domain answer, not a broken machine.
Guards on one state must *all* pass (separate rules; `&&`-ing them loses which said no). `after()` runs after
persistence, so a hook cannot fire for a save that rolls back. `allow()` takes a list of origins because
"anything up to here may be cancelled" is the common shape.

`TransitionAction::to($state)->workflow($machine)` is an ordinary action that does two more things by itself,
so **no surface needs to know a workflow exists**: it hides where the move would not work (`isHidden($record)`,
which every action surface already asks, and `canExecute($record)` before running), and pressing it performs
the transition (attaching a machine sets the action callback; an explicit `->action()` still wins, either
order). `isAvailableFor($record, $user)` answers the machine's half alone. An action for a transition the user
cannot complete exists to be refused, and an unpredictable refusal reads as an application bug. Label / colour
/ icon come from the target enum via the same resolution BadgeColumn uses, so button and badge cannot
disagree; `->visible()` and the machine's answer stay separate so neither overrules the other, and a
record-less check leaves the machine out of it.

## Domain modules

A **domain module** is the second axis — `billing` beside `operations` — and it is a **plugin**, not a parallel
registration system: it registers from `config('wire-core.plugins')` when an application declares it, or from a
package provider's `$this->app->resolving(PluginManager::class, …)` when a package ships one; `PluginManager` gives it one id, the
register-then-boot lifecycle and `dependencies()` checking, and **there is deliberately no ModuleRegistry**
(that list already exists). `DomainModule` only *declares* — `resources()`, `dashboards()`,
`navigation(): ?NavigationGroup` — and `WireCoreServiceProvider::bootModules()` spreads those into the three
registries. **Never make a module reach for `DashboardRegistry` itself**: a dashboard is L2 and the module
contract is L1, so naming classes is what keeps the layer test green. Not a module's job: workflows (the
resource carries one), policies (Laravel's Gate), workspaces (a service over the registries). `describe-module`
reports what each declares. **A package adds, it never overwrites**: two classes on one key are refused, so an
application adjusts a shipped module through a **`table.composing`** / **`form.configuring`** /
**`infolist.configuring`** hook scoped with `for: '<key>'`, never by subclassing its resource (a subclass keeps
the parent's key).

### Plugins and hooks

A plugin is `getId()` + `register(PluginManager)` + `boot(PluginManager)`, registered from
`config('wire-core.plugins')` or from a package provider's **register** phase. `register()` must not resolve
services (they may not be bound yet); `boot()` runs after every plugin is registered — and **closes
registration**: `PluginManager::register()` after `boot()` throws `PluginRegistrationException`, because a
plugin arriving then is never booted and a module's declarations never reach the registries. Never register a
plugin in a provider's `boot()`, and never defer a provider that registers one. A config entry that cannot be a
plugin is refused too (blank strings excepted), rather than skipped. `HasDependencies::dependencies()` lists ids that must already be registered —
checked at registration, so **list a dependency before its dependant in config** or registration throws.
`HasConfiguration` merges `defaultConfig()` under `wire-core.plugins.config.{id}`.

**A hook does nothing until something runs it.** `hook('name', $cb)` stores a callback; behaviour changes only
where a `runHook()` / `runTypedHook()` call exists. Callbacks run by ascending priority; an array-hook callback
returning an array replaces the payload, anything else leaves it unchanged. Names come from the `Hook` enum
(strings still accepted). **`table.composing` ≠ `table.configuring`**: composing runs once on the table instance
the host built (a column added there renders), configuring runs in `TableQueryService` on arrays the planner
consumes (a column added there is searched and sorted on and never drawn). `form.configuring` is the form's
composing hook, `infolist.configuring` the detail page's. Eight hooks are **typed-only** — those three plus
`widget.configuring` (before widget keys are stamped and before visibility filters), `export.configuring` (in
`buildTableExport()`, so a download and a queued file are one export), `navigation.building` (the flat entry
list, before grouping), `page.mounting` (last in a resource page's mount, so the record is resolved) and
`search.querying` (per resource, before `get()` and before the policy check). No array counterpart, and new
hooks stay that way. `hook(..., for: 'invoices' | Model::class | Page::class)` scopes a callback to one
component through the payload's `HookTarget`; a scoped callback is skipped when a dispatch carries no target.
Two hooks belong to no component and name something else: `navigation.building` takes a **zone**,
`search.querying` the searched resource's catalogue key. **Never write the container/`hasHook()` guard by
hand** — `HookDispatch::typed($hook, fn () => new …Payload(…))` owns it, and its `null` means "nobody
listened", never "nothing changed" (folding the two with `??` undoes a callback that emptied the array).

**The rule that cost this repo a defect: the first parameter's type hint decides which dispatcher a callback
belongs to.** Every built-in lifecycle point (`table.configuring|querying|queried`, `form.saving|saved`,
`action.executing|executed`) runs **both** dispatchers back to back, so each callback must belong to exactly
one:

| First parameter | Dispatcher | Receives |
|---|---|---|
| `array` | `runHook()` | the payload array |
| a DTO (`TableQueryingPayload`, `FormSavingPayload`, …) | `runTypedHook()` | the object |
| **no type hint, or no parameter** | `runHook()` | the array — **never the DTO** |

**Always type-hint the first parameter.** An unhinted `function ($payload)` used to answer "no" to both
questions, so *both* dispatchers ran it: every side effect happened twice, and the second pass handed it a DTO
where `$payload['data']` fatals — after the callback had already done its work. Unhinted now falls to the array
side deliberately, because that is what a callback written before DTOs existed expects.

**Do not reach for a hook to enforce something.** A hook covers one path and only while a PluginManager is
bound. Security-shaped behaviour belongs lower: tenancy is a global scope, not a `table.querying` hook — see
below.

### Multi-tenancy

Off by default (`wire-core.tenancy.enabled`), **strict once on**. Bind a `TenantResolver`; the shipped default
answers null. Mark models with `BelongsToTenant` — opt-in per model, because the framework cannot know which
tables are tenant-owned and guessing would be a guess about who may see what.

**The fail-safe is the whole story: tenancy on with no tenant resolved returns NOTHING, never everything.**
Every ordinary state produces a null tenant (before login, a worker, a console command), so reading null as "no
constraint" hands every row to every one of them. The scope emits `0 = 1`. It is deliberately **not**
`where tenant_id is null` either — an unowned row would then be visible to everybody.

**A non-Eloquent source builds no query, so no scope reaches it** — wrap it: `new TenantScopedDataSource($source, app(Tenancy::class))`. It constrains the **plan**, not the returned rows, which is what makes it safe on `count()` and `paginate()` too (those answer without handing rows over) and lets a source that cannot honour the filter refuse out loud. `resolveRecord()` takes a key rather than a plan, so there the record is fetched and checked — without that a tenant reaches another tenant's row by typing its id into a URL. Same fail-safe, and tenancy off delegates untouched.

**A global scope, not a plugin hook.** A hook covers one read path and only while a PluginManager is bound; a
global scope covers every query Eloquent builds — a listing, a relation, `find()` in the app's own controller,
`update()`, `delete()`, and a queued job resolving by key. `create()` is attributed to the current tenant and
**throws** when none resolves, because a row with a null tenant is invisible to every scoped query afterwards:
the user's work is gone and nothing said so. An explicitly set column is left alone (seeder, deliberate move).
The column is qualified — a scoped model is routinely joined and joined tables carry their own `tenant_id`.

`Model::acrossAllTenants()` steps past it, verbosely and greppably, for admin reports and console commands.
**Not covered:** a non-Eloquent `DataSource` has no Eloquent query to scope — constrain it in the source.

Resolve `Tenancy` per query, never hold it: a global scope is added once per model class per process, so a
captured instance answers with whoever was current the first time that model was touched — wrong on the second
Octane request and every job after the first.

### Queued actions

`->queue()` / `->onQueue('reports')` / `->onConnection('redis')` on any action (naming a queue or connection
implies `->queue()`). **Default stays synchronous and should** — a user clicking Delete expects the row gone on
return; this is for the long tail (bulk over ten thousand rows, an export that would time out).

**The job carries names and keys, never objects**: host class, action name, record keys, form data — all
scalars. Not the action (closures), not models (stale by the time a worker takes them; ten thousand would be a
megabyte of payload). It rebuilds the host, calls `resolveActionByName()`, and reads records fresh via
`resolveRecordsByKey()` — so a row edited between click and run is acted on **as it is at run time**. A single
key still arrives as `$record`, a set as `$records`.

**A queued action has no browser.** `$set` / `$setParent` / `$setFrame` / `$close` / `$replace` / `$halt` are
bound to **throw** `QueuedActionException`, never to no-op — a silent `$close()` looks like it worked and
surfaces weeks later as "the modal never closes". Report back with a notification; that is what the database
driver is for, since the request that queued the job is gone by then. An action renamed or removed between
dispatch and run throws too.

`RunActionJob` reaches Notifications by class name, not import: both are L2 and ADR 0025 forbids L2→L2 — the
same soft seam `HasLifecycle::resolveNotificationManagerClass()` uses.

### Notifications

`Notification` is an immutable value object dispatched through a driver (current-component, session, livewire,
flasher, **database**, null), selected by `wire-core.notifications.default` — which takes a **list** as well as
a string, so `['session', 'database']` shows the toast *and* keeps it in the bell (a `StackDriver` fans out;
one driver throwing does not silence the rest, and the failure is re-thrown after all have had their turn). The built-in default is `CurrentComponentDriver`
(decorates `SessionDriver`): it resolves the active Livewire component via `Livewire::current()` itself, so
`NotificationManager::send($notification)` and the `InteractsWithNotifications`/`sendNotification` helpers no
longer thread `$this`. A custom per-component driver that needs the component must wrap itself in
`CurrentComponentDriver`.

**Persistent notifications.** The transient drivers deliver to the page being rendered — useless for a queued
export finishing twenty minutes later, when there is no component to dispatch to. `DatabaseDriver` writes the
row instead; `NotificationCenter` reads it and `@@livewire('wire-notification-bell')` renders it. Every read is
scoped to the recipient resolved by `ResolvesNotifiable` (default: the authenticated user), **`markAsRead($id)`
included** — the id comes from a Livewire action, so an unscoped lookup would let one user mark another's
notification read. With no recipient the driver writes **nothing** rather than storing an unreachable row,
which is the ordinary state on a queue worker; bind your own resolver when a job must address someone. The
table matches Laravel's `notifications` shape so an app can share its own, and the id is a **ULID** in that
uuid column: a bulk job puts five rows in one second, where `created_at` alone orders them arbitrarily.

Fluent `Notification`: `->title()`, `->duration(ms)`, `->icon()`, `->position()`, `->persistent()` (sticky,
duration 0, no countdown bar), and `->action('Undo', 'event')` / `->action(NotificationAction::make(...))` —
action buttons dispatch a Livewire event on click (host listens with `#[On('event')]`). `NotificationAction`
supports `->payload([...])`, `->color()`, `->keepOpen()`. The built-in drivers forward the full payload, so
titles/actions/persistence survive the server round-trip.

Toast container: @verbatim`<x-wire-notifications::toast-container />`@endverbatim — props `position`, `duration`, `event-name`,
`session-key`, `stack` (collapse into a pile that fans out on hover), `progress` (per-toast countdown bar, hover
pauses it and the auto-dismiss), `max` (cap visible toasts, overflow into a "+N more" pill). Honors
`prefers-reduced-motion` and exposes an `aria-live` region. It renders the flashed notification too, which is how
a toast raised by a request that then redirected — `successRedirect()`, a create page landing on its new record —
is shown at all: the event died with the document, the flash crossed.

### Infolists

Read-only counterpart of forms. `Infolist::make()->schema([...])` with entries: `TextEntry`, `BadgeEntry`,
`IconEntry`, `BooleanEntry`, `ListEntry`, `ImageEntry`, `ColorEntry`, `KeyValueEntry`, `RepeatableEntry`.
Layouts: the shared vocabulary above (`Section`, `Grid`, `Fieldset`, `Flex`, `Tabs`, `Wizard`, `Callout`,
`EmptyState`) — see the Layouts section. Integrates with `ViewAction->infolist()`.

Actions: `Section::headerActions([...])`, `Entry::actions([...])`, and `RepeatableEntry::actions([...])`
(per-row, gets the row `$record`) — dispatch via the host's `callInfolistAction()` (works in an action modal /
`WithActions` host); names must be unique per infolist. `RepeatableEntry::with([...])` eager-loads relations on
the rows to avoid N+1 when child entries read nested relation paths.

### Editable panels

An infolist you can edit: same declarative schema, but editable entries write **straight back to the
record**, one commit per change — no Save button, no form buffer. `Panel::make()->record($model)->schema([...])`
(namespace `Panels\`, `PanelComponent` base or compose `Panels\Concerns\WithEditablePanel` into an existing
component and implement `panel()`). The view must `@@include('wire-core::partials.floating-assets')` to load
the shared `wireEditableCell` engine.

Editable entries (`Panels\Components\`): `ToggleEntry`, `CheckboxEntry`, `SelectEntry`, `TextInputEntry`.
They extend the infolist `Entry`, so read-only entries (`TextEntry`, `BadgeEntry`, …) mix freely into the
same schema. Same write path — optimistic UI + optimistic locking — as editable table columns
(`ToggleColumn`/`SelectColumn`/`TextInputColumn`); do not invent a second one.

`->rules([...])` validates server-side before the write. `->disabled()` (closure gets the `$record`) and
`->permission('ability')` reject the write server-side, not just cosmetically. Being declared an editable
entry in the schema **is** the write whitelist: a read-only entry name, or any attribute not in the schema,
is refused by the host. Override persistence with `->saveUsing()`, run side effects with `->afterStateUpdated()`.

Choose: infolist = read-only by contract; panel = read *and* edit one record in place; form = buffered
multi-field edit with one Save.

### Widgets

`StatsOverviewWidget` / `Stat`, `ChartWidget` (+ `LineChartWidget`/`PieChartWidget`/`DoughnutChartWidget`
presets and `->options([...])` Chart.js overrides), `BarChartWidget` (pure-CSS bars: `->type('vertical'|'horizontal')`, `->variant('finance'|'system')`, `->showGrid()`, `->verticalLabels()` to rotate each bar's label beside it for long names), `CustomWidget` — and
`TableWidget`, which is **`NyonCode\WireTable\Widgets\TableWidget`, not wire-core's**: it draws rows of a
table inside a card and the engine that draws them is wire-table's, so the class went with it in 2.0. A
`use NyonCode\WireCore\Widgets\TableWidget;` is the class that was removed.

**Never write the host's widget state from a view or from JS.** `widgetLayoutDraft`, `editingWidgets`,
`widgetFilters`, `loadedWidgets` and `dashboardFilters` are `#[Locked]`: every change has a method (`moveWidget`, `placeWidget`,
`resizeWidget`, `removeWidget`, `start/save/cancel/resetWidgetLayout`, `filterWidget`, `loadWidget`,
`setDashboardFilter`, `resetDashboardFilters`), and
those are where a key is checked against the declaration and a size against the grid. A `wire:model` on any
of them throws at runtime.

**More than one arrangement** is `savedLayouts(): true` beside it — a separate opt-in, because a switcher
is not what a dashboard with one layout wants. It rides the same store under a name; the host gains
`saveWidgetLayoutAs()`, `applyWidgetLayout()`, `deleteWidgetLayout()` and `getWidgetLayoutNames()`, and the
shipped controls gain a "Save as" and a select. Applying a saved layout **copies** it onto the current one
rather than pointing at it, so nothing has to remember which name is in use.

**A dashboard a user may rearrange** says `customisable(): true` on the `Dashboard` (off by default; the
stored layout is per user, under the dashboard's key, in `wire-core.preferences`). Then **every widget on
it needs its own `key()`** — a derived key is a position, and a stored layout addresses widgets by key, so
a missing one is refused rather than rendered. `->group('Money')` is the heading it is offered under in the
tray; `->sizes([[2, 1], [4, 2]])` is the list of sizes it may be given, and it binds: the resize buttons
walk exactly those pairs and the first one is the size it arrives at from the tray.
Key the pairs to name them (`->sizes(['S' => [1, 1], 'M' => [2, 1], 'L' => [4, 1]])`) and the editor offers
`S M L` buttons instead of steppers; `->description()` is shown under the heading in the tray.

**Shape a customisable dashboard on the `Dashboard`, not in its page:** `defaultLayout()` is what somebody
sees before arranging anything (`['kpi' => 'L', 'queue' => [2, 1], 'money']` — may depend on the user; the
rest starts in the tray, Reset comes back here), `autosave()` stores every change at once (the mode then ends
with Done, no Save/Cancel), `maxWidgets()` refuses a widget past the limit. A **filter over the whole
dashboard** is `filters(): [DashboardFilter::make('period')->options([...])->default('month')->buttons()]`,
read in `widgets()` through `$this->filter('period')` — one selection in the address (`?dashboard[period]=…`)
that every widget answers, never a per-widget `filter()` repeated on each. A widget that cannot be narrowed
says `->ignoresDashboardFilters()` and is marked while a filter narrows. A hand-written `WithWidgets` host
answers the same through `defaultWidgetLayout()`, `autosavesWidgetLayout()`, `maxWidgets()`,
`getDashboardFilters()` and `$this->dashboardFilter()`, and includes `wire-core::widgets.partials.widget-filters`.

**A `columnSpan()` is resolved against the grid the component lands in, and capped by it.** Write the
number (`columnSpan(3)`, `columnSpanFull()`) and the breakpoints follow the grid — the layout tells each
child what it is drawn in, and `HasColumnSpan::getColumnSpanClass()` steps the span with the columns. Never
hand-write `col-span-*` on a schema or infolist child, and never assume a span wider than the grid clips:
CSS Grid adds the missing column instead, which re-flows the layout and squeezes every sibling into the
remainder. A grid of your own gets its classes from `ResponsiveGrid::cols()` and hands the same argument to
`ResponsiveGrid::span()`; the two shipped ladders are `fieldColumns()` (fields, two columns from `sm`) and
`cardColumns()` (cards and widgets, two from `md`).

**Never let a widget span more columns than the dashboard declares.** `columns(3)` means three is the
widest a tile can be — a wider one does not clip, CSS Grid adds the missing track and squeezes every other
tile on the page into what is left. The steppers and the server both cap at `columns()` for you
(`WidgetSizeOffer`), so the trap is only in hand-written markup: a widget grid resolves its spans through
`ResponsiveGrid::span($span, $ladder)` against the same ladder `ResponsiveGrid::cols()` built, never
through a bare `col-span-*` or `HasColumnSpan::getColumnSpanClass()`, which answers for a grid that ramps
at `sm` and knows no column count.

### Audit log

Add `HasAuditable` to a model and its created/updated/deleted changes persist as `AuditEntry`
rows automatically — the package registers the event subscriber itself, gated by
`wire-core.audit.enabled`. No manual `Event::subscribe()` needed. Retention: configure
`wire-core.audit.retention_days` and schedule `wire-core:audit-prune` (or run with `--days=N`).
A period under one day is refused — `--days=0` exits non-zero and deletes nothing, and `prune(0)`
throws `InvalidRetentionException` — so never use `0` to mean "prune everything".
Suppress logging in seeders/imports with `AuditLogger::withoutAuditing(fn () => …)`.

**The trail sees model writes and nothing else.** `Order::query()->update()`, `->delete()`,
`->increment()`, `insert()` and `upsert()` fire no model event and leave no entry. When a write
has to be audited, loop the models, or dispatch `RecordUpdated` / `BulkActionExecuted` for it.
Do not wrap a query-builder write in `withoutAuditing()` — it was never going to be recorded.
Soft deletes need nothing extra: `restore()` is an `updated` entry, `forceDelete()` a `deleted` one.

**Credentials never reach the trail.** Passwords, anything ending in `_token` or `_secret`,
two-factor columns, recovery codes and `api_key` are dropped by the logger itself — a floor, not a
default, so emptying `wire-core.audit.exclude_columns` does not bring them back. That list adds
application-specific columns on top and accepts `*` (`'billing_*'`). Do not add the credential names
to it "to be safe"; do add your own (`salary`, `national_id`). The entries are rendered old-value
beside new-value on the audit module's screen, so anything you leave in is on a page.

### Tours

A `Tour` is a guided walkthrough registered from a service provider's `boot()`:
`$this->app->make(Tours::class)->register(Tour::make('id')->steps([TourStep::make('table-search')->text('…')]))`.
It runs by itself on the first full page render of a screen it claims, and the browser does the rest;
finishing and skipping both record it, in one Livewire request.

**A step names an element hook, never a selector.** `TourStep::make()` takes a `data-wire` name
(`admin-sidebar`, `table-search`, `admin-nav-item`) and throws on anything that is not kebab-case.
Find real names with `grep -rho 'data-wire="[a-z-]*"' vendor/nyoncode | sort -u` — do not invent one,
because a well-formed name nothing renders is silently **skipped**, exactly like an element that is
hidden (an unopened dropdown, the bulk bar before a selection). Narrow one element among many with
`->where('resource', 'orders')` (matches `data-resource="orders"`).

**Scope with what already exists.** Who: `->permission('sales.*')`, `->authorize()`,
`->authorizeUsing()`, `->visible()` — the shared authorization, through `Gate`, wildcards included.
Do not add a role check of your own. Where: `->zones('sales')` (`->zones(null)` is the unzoned app),
`->resource('orders')`, `->page('index')`. Several matching tours: the lowest `->sort()` runs, the
rest wait for a later visit — one tour per audience, never one tour with branches.

**Show it again by changing `->since()`.** The stored value is compared for inequality, so any
different string re-runs it for everybody who finished the old one. Never rename the id for that —
the id is what acknowledgements are stored against. Storage is `wire-core.tours.preferences`
(default `session`; `database` plus the `wire-core::migrations` publish for "once, ever").
Below the mobile sheet breakpoint the panel docks to the bottom and the page scrolls each element
above it; what a phone hides (the sidebar drawer) is skipped and not counted. `TourStep::on('orders')`
puts a step on another page of the same zone — "Next" navigates there and the tour carries on; a step
whose page's route (`can:` middleware, via core's `AuthorizesUrls`) refuses this person is skipped. A tour
left halfway reopens at the step reached (stored per `since()`; cleared by finish, skip and replay). The
framework registers no tour itself.

**A tour may ask before it points.** `->welcome(TourWelcome::make()->heading('…')->text('…'))` opens it
with a centred card offering Start and Later. **Later is not Skip**: skip is recorded exactly as finishing
is, while later puts the tour down for that session and is counted — the one reaching `->postpone(int)`
(default `wire-core.tours.postpone`, 3) acknowledges it, so a greeting cannot return for ever.
`->postpone(0)` drops the Later button. The card is shown only when the tour starts from the top, never
when resuming. `TourWelcome::view('tours.welcome')` swaps the markup for your own, included inside the
tour's Alpine scope, where it has `greeting`, `begin()`, `later()` and the `welcome` payload and owns its
own `x-show`. Hooks: `tour-welcome`, `-heading`, `-text`, `-start`, `-later`.

**A tour is drawn by the layout, through `PageChrome`** — `wire-admin`'s renders it, and a layout of
your own must render the same two loops (body, and `PageChrome::USER_MENU`) plus `@@wireStackScripts`
in the head, or nothing appears however well the tour is scoped. That registry is also where the
"Replay the tour" entry in the user menu comes from; it shows only on a screen a tour claims. For a
replay trigger anywhere else, return `app(TourState::class)->replayNow('id', auth()->user())` from the
action: it forgets the tour *and* answers with a redirect to the screen the tour runs on, built from the
tour's own scoping through the router — never from an address held in a public Livewire property, which
is writable from the browser. It answers null when the tour names no page of its own or the person may
not open it, and the tour is forgotten either way, so reload when null. `replay()` is the same without
the redirect, for a trigger on a screen the tour already claims.
`TourState::acknowledge('id', $user)` is the lever the other way, which is what a test uses to assert
somebody already-seen is left alone.

### JavaScript assets

Put `@@wireStackScripts` once in the layout `<head>`. It emits every registered wireStack Alpine
controller (dropdown/tabs/wizard/editable-cell/fill-handle from core, plus whatever `table`,
`forms` and `sortable` registered), so they survive `wire:navigate`. Narrow it with
`@@wireStackScripts('wire-table')` if you only want one package. Apps that do not add it still
work — each surface `@@include`s its own asset partial as a fallback — but a SPA app **should**
add it: without it a bundle first reaching the page via `wire:navigate` may lose the race on the
cached Back/Forward path, where Livewire does not wait for newly injected head scripts before
initialising Alpine.

The tag itself is `nyoncode/laravel-package-toolkit`'s (`PackageAssets`); `@@wireStackScripts` is a
thin alias for its `@@packageAssets`, kept because it is already in consuming layouts. A package
declares its own bundles in its own `configure()`; core never learns about downstream packages:

```php
$packager
    ->bootedPackage(fn () => Bundle::serve('wire-table', self::ASSETS_PATH))
    ->hasAssets('dist', entries: [
        Bundle::make('wire-table-records.js'),
    ])
    ->hasAssetFallback(Bundle::servedByRoute('wire-table'));
```

`Bundle` (core) is the one place that knows what shape a wireStack bundle is: `classic()`, because
every bundle is an esbuild IIFE and the toolkit would otherwise emit `type="module"` — a module is
deferred and its top-level declarations never reach `window`, so the registrar below would register
nothing and every `x-data` would die with no error at the point of the mistake. It also removes the
`defer` that `classic()` adds by default, and adds `data-navigate-once`.

Entries are keyed by the **shipped filename**, not a short id. Delivery is the toolkit's: the mirror
copies `dist/` into `public/vendor/{package}` on first resolve — no `vendor:publish`, no build step
for consumers — and `hasAssetFallback()` points at the package's own `{package}.asset` route for the
app whose `public/` cannot be written. Without that fallback the renderer drops the tag silently.

**`Bundle::serve()` registers that route — never hand-write it.** It is the other half of the same
mapping: `servedByRoute()` reads a bundle id off a shipped filename to build the URL, `serve()`
turns that id back into the file, and one class owning both is what keeps them from drifting. It
carries the parts a hand-written `Route::get` gets wrong — a 404 rather than a 500 for a bundle the
package does not ship, an `[A-Za-z0-9_-]+` id pattern, and the immutable cache header that stops a
fallback the renderer reaches on every page from costing a request every page. Name the bundle
`{package}-{id}.js`, after the package itself, or anything else: all three round-trip.

**Register Alpine components unconditionally, never only inside `alpine:init`.** That event fires
exactly once per document, so a bundle arriving later (SPA navigation, a lazily rendered table, an
AJAX-loaded modal) would subscribe to an event that never fires again and register nothing —
`x-data="wireX(...)"` then dies with `wireX is not defined`. The canonical idiom, used by every
bundle in the repo:

```js
let registered = false
const register = () => {
    if (registered || ! window.Alpine) return
    registered = true
    window.Alpine.data('wireX', wireX)
}
if (window.Alpine) register()
else document.addEventListener('alpine:init', register)
```

The `registered` guard is load-bearing, not defensive: the directive and a per-surface partial can
both emit the same `src`, so the bundle may execute twice.

**Declaring the entry is not enough — the surface still has to include its own `@@assets` partial.**
`@@wireStackScripts` is *additive*: an app that never puts it in a layout is supported, and then the
declaration delivers nothing. A view whose `x-data` calls a factory from a bundle nobody delivered
evaluates against an empty registry and the component silently does nothing — no exception, no
console error at the point of the mistake. This is invisible to PHP tests, which read the markup and
find it correct; only `npm run verify:drivers` catches it. So a new controller bundle needs both: the
`Bundle::make()` entry, and an `@@assets`/`@@packageScripts` partial included from every view that
uses it (`wire-core::partials.floating-assets`, `wire-forms::partials.field-assets`).

Core interaction controllers are **never** lazy per-component — that is what causes the bug above.
Lazy is for heavy, optional bodies only: TipTap is the one case, and it stays outside the entry list
entirely, delivered by the field that needs it. Lazy-load bodies, never registrators.

### Browser-testing hooks

Every interactive control across the shared UI carries a stable `data-testid` (+ an accessible name/role where icon-only), so Pest v4 Browser Testing targets it at the user level: modals (`modal-close`, `slide-over-close`, `modal-cancel`/`modal-submit`/`modal-back`/`modal-next`, `confirmation-confirm`/`confirmation-cancel`, `modal-footer-action-{name}`), layout (`wizard-step-{i}`/`wizard-back`/`wizard-next`, `tab-{i}`, `section-toggle`, `callout-dismiss`), toasts (`toast-dismiss`, `toast-action-{i}`, `toast-expand`), the searchable select (`select-trigger`/`select-search`/`select-option-{value}`/`select-clear`), actions (`action-{name}` + header/bulk/menu variants), and infolist actions (`infolist-action-{name}`). Actions and options are also reachable by visible text/role.

### Icons & colors

Icons resolve by name through the `IconManager` (bundled Heroicons solid + `outline:` prefix). Use
`list-icons` to find a name. In a Blade view use @verbatim`<x-wire::icon name="check" class="w-5 h-5" />`@endverbatim
— the component API, which also forwards Alpine/`data-*` attributes onto the `<svg>`. In **custom
column / entry / partial views** rendered per row, prefer the `icon()` helper —
@verbatim`{!! icon('check', 'w-5 h-5') !!}`@endverbatim — a plain PHP function returning the cached
`IconManager` `<svg>` string (no per-row view render, unlike the component); pass `$attributes` (5th
arg) for an Alpine-bound icon. Never hardcode an inline `<svg>` (breaks theming). Colors and sizes are
semantic tokens owned by the Foundation palette.
`->color()` accepts the full Tailwind palette on every surface — the semantic roles (`primary`,
`success`, `danger`, `warning`, `info`, `gray`), every raw hue family (`blue`, `green`, `red`, `yellow`,
`cyan`, `slate`, `zinc`, `neutral`, `stone`, `orange`, `lime`, `teal`, `sky`, `indigo`, `violet`,
`purple`, `fuchsia`, `pink`, `rose`) and the adaptive achromatic endpoints (`white`, `black`), as a
string or the matching `Foundation\Colors\Color` enum case. The literal hues are NOT aliases: `blue` is
distinct from the re-themeable brand `primary`, `green` from `success`/`emerald`, `yellow` from
`warning`/`amber` (only `emerald`/`amber`/`secondary` remain true role aliases). `white`/`black` resolve
adaptively — dark in light mode, flipped in dark mode. Resolvers live in `HasColor`; unknown names fall
back to gray.

Every fluent token setter also accepts a canonical enum from `Foundation\Enums\` (interchangeable with
the string, so both forms are fine): `Breakpoint` (`sm`…`2xl`) for column `visibleFrom()`/`hiddenFrom()`/
`mobileBreakpoint()` + `stackedOnMobile()`, `Size` (`xs`…`xl`) for `size()`, `FontWeight` (`thin`…`black`)
for `weight()`, `Alignment` (`left`/`center`/`right`) for `alignment()`/`actionsAlignment()`, `IconPosition`
(`before`/`after`) for `->icon($icon, $position)`, `Placement` for `ActionGroup::dropdownPosition()`, and
`ModalWidth` (`sm`…`7xl`/`full`) for modal `width()`. Each enum owns its vocabulary (`values()`/`resolve()`)
and, where relevant, the literal Tailwind class its tokens map to — extend the enum, not a local `match`.

- **Resources and dashboards register from a list or from a folder.** `config('wire-core.resources')` /
  `dashboards` list classes; `config('wire-core.discover')` maps a namespace to a directory
  (`'resources' => ['App\\Resources' => app_path('Resources')]`) and every concrete class of the kind under it
  registers at boot, after the lists (a class in both registers once). It goes through the autoloader
  (`Foundation\Registration\ClassDiscovery`) — never scan a folder of your own for the same purpose.

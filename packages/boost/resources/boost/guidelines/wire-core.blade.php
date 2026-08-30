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

- Presets: `DeleteAction`, `EditAction`, `ViewAction`, plus bulk presets (`DeleteBulkAction`, …).
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
`stack` (collapse into a pile that fans out on hover), `progress` (per-toast countdown bar, hover pauses it and
the auto-dismiss), `max` (cap visible toasts, overflow into a "+N more" pill). Honors `prefers-reduced-motion`
and exposes an `aria-live` region.

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
presets and `->options([...])` Chart.js overrides), `BarChartWidget` (pure-CSS bars: `->type('vertical'|'horizontal')`, `->variant('finance'|'system')`, `->showGrid()`, `->verticalLabels()` to rotate each bar's label beside it for long names), `TableWidget`, `CustomWidget`.

### Audit log

Add `HasAuditable` to a model and its created/updated/deleted changes persist as `AuditEntry`
rows automatically — the package registers the event subscriber itself, gated by
`wire-core.audit.enabled`. No manual `Event::subscribe()` needed. Retention: configure
`wire-core.audit.retention_days` and schedule `wire-core:audit-prune` (or run with `--days=N`).
Suppress logging in seeders/imports with `AuditLogger::withoutAuditing(fn () => …)`.

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

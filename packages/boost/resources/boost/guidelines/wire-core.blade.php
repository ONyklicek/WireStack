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
- Actions can open modals via `->modal(...)` and multi-step wizards via `->steps([...])`.
- A wizard step's `->schema(fn (array $data) => [...])` Closure builds its fields from data entered in
  earlier steps; the bag is live even for header actions (no record), so later steps can branch on it.
- An action with a form (`->form([...])`) self-seeds each field from its `->default()` (or a
  type-correct blank), then layers record/context prefill on top with `->fillFormUsing(fn ($record) => [...])`
  (`$record` is `null` for header actions), which overrides the seed. Inside the form, reactive fields use `$get`/`$set` and
  `->afterStateUpdated()` against the live `modal.action.formData` bag — see the wire-forms guideline.
- Add extra footer buttons with `->modalFooterActions([ModalFooterAction::make('preview')->action(fn ($data, $set) => …)])`.
  The callback gets the live form `$data` and a `$set` writer; `->submitsForm()` validates first,
  `->closesModal()` closes after, `->position('before'|'after')` places it around Cancel/Submit.
- Modals **stack**: opening an action while a modal is open (from any callback with the host `$component` —
  a footer/field/infolist action calling `$component->mountAction(...)` / `$component->openActionModal(...)`)
  layers the new modal on top with the parent dimmed behind. Closing the top (Escape/close/backdrop) pops
  just that level and resumes the parent with its form data intact. No special API; deep nesting works
  (capped at `Modals\ModalStack::MAX_DEPTH` as a runaway guard), and only the top modal takes focus/Escape.
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

`Modal`, `ConfirmationDialog`, `SlideOver` and `Wizard`. Prefer attaching a modal to an action over
building bespoke modal state. Mobile presentation is per action: `->slideOverOnMobile()` renders the
form modal as a bottom-sheet that slides up from the bottom edge, `->fullScreenOnMobile()` fills the
viewport; both keep the centered dialog on desktop and scroll the body inside the panel. Combine
`->slideOver()->slideOverOnMobile()` for a desktop slide-over that becomes a mobile bottom-sheet.

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

### Notifications

`Notification` is an immutable value object dispatched through a driver (current-component, session, livewire,
flasher, null), selected by `wire-core.notifications.default`. The built-in default is `CurrentComponentDriver`
(decorates `SessionDriver`): it resolves the active Livewire component via `Livewire::current()` itself, so
`NotificationManager::send($notification)` and the `InteractsWithNotifications`/`sendNotification` helpers no
longer thread `$this`. A custom per-component driver that needs the component must wrap itself in
`CurrentComponentDriver`.

Fluent `Notification`: `->title()`, `->duration(ms)`, `->icon()`, `->position()`, `->persistent()` (sticky,
duration 0, no countdown bar), and `->action('Undo', 'event')` / `->action(NotificationAction::make(...))` —
action buttons dispatch a Livewire event on click (host listens with `#[On('event')]`). `NotificationAction`
supports `->payload([...])`, `->color()`, `->keepOpen()`. The built-in drivers forward the full payload, so
titles/actions/persistence survive the server round-trip.

Toast container: `<x-wire-notifications::toast-container />` — props `position`, `duration`, `event-name`,
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

### Widgets

`StatsOverviewWidget` / `Stat`, `ChartWidget` (+ `LineChartWidget`/`PieChartWidget`/`DoughnutChartWidget`
presets and `->options([...])` Chart.js overrides), `BarChartWidget` (pure-CSS bars: `->type('vertical'|'horizontal')`, `->variant('finance'|'system')`, `->showGrid()`, `->verticalLabels()` to rotate each bar's label beside it for long names), `TableWidget`, `CustomWidget`.

### Audit log

Add `HasAuditable` to a model and its created/updated/deleted changes persist as `AuditEntry`
rows automatically — the package registers the event subscriber itself, gated by
`wire-core.audit.enabled`. No manual `Event::subscribe()` needed. Retention: configure
`wire-core.audit.retention_days` and schedule `wire-core:audit-prune` (or run with `--days=N`).
Suppress logging in seeders/imports with `AuditLogger::withoutAuditing(fn () => …)`.

### Browser-testing hooks

Every interactive control across the shared UI carries a stable `data-testid` (+ an accessible name/role where icon-only), so Pest v4 Browser Testing targets it at the user level: modals (`modal-close`, `slide-over-close`, `modal-cancel`/`modal-submit`/`modal-back`/`modal-next`, `confirmation-confirm`/`confirmation-cancel`, `modal-footer-action-{name}`), layout (`wizard-step-{i}`/`wizard-back`/`wizard-next`, `tab-{i}`, `section-toggle`, `callout-dismiss`), toasts (`toast-dismiss`, `toast-action-{i}`, `toast-expand`), the searchable select (`select-trigger`/`select-search`/`select-option-{value}`/`select-clear`), actions (`action-{name}` + header/bulk/menu variants), and infolist actions (`infolist-action-{name}`). Actions and options are also reachable by visible text/role.

### Icons & colors

Icons resolve by name through the `IconManager` (bundled Heroicons solid + `outline:` prefix). Use
`list-icons` to find a name. Colors and sizes are semantic tokens owned by the Foundation palette.
`->color()` accepts the full Tailwind palette on every surface — the semantic roles (`primary`,
`success`, `danger`, `warning`, `info`, `gray`) and every raw hue family (`slate`, `zinc`, `neutral`,
`stone`, `orange`, `lime`, `teal`, `sky`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`),
as a string or the matching `Foundation\Colors\Color` enum case. Resolvers live in `HasColor`; unknown
names fall back to gray.

Every fluent token setter also accepts a canonical enum from `Foundation\Enums\` (interchangeable with
the string, so both forms are fine): `Breakpoint` (`sm`…`2xl`) for column `visibleFrom()`/`hiddenFrom()`/
`mobileBreakpoint()` + `stackedOnMobile()`, `Size` (`xs`…`xl`) for `size()`, `FontWeight` (`thin`…`black`)
for `weight()`, `Alignment` (`left`/`center`/`right`) for `alignment()`/`actionsAlignment()`, `IconPosition`
(`before`/`after`) for `->icon($icon, $position)`, `Placement` for `ActionGroup::dropdownPosition()`, and
`ModalWidth` (`sm`…`7xl`/`full`) for modal `width()`. Each enum owns its vocabulary (`values()`/`resolve()`)
and, where relevant, the literal Tailwind class its tokens map to — extend the enum, not a local `match`.

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
- An action with a form (`->form([...])`) seeds initial values with `->fillFormUsing(fn ($record) => [...])`
  (`$record` is `null` for header actions). Inside the form, reactive fields use `$get`/`$set` and
  `->afterStateUpdated()` against the live `modal.action.formData` bag — see the wire-forms guideline.
- Add extra footer buttons with `->modalFooterActions([ModalFooterAction::make('preview')->action(fn ($data, $set) => …)])`.
  The callback gets the live form `$data` and a `$set` writer; `->submitsForm()` validates first,
  `->closesModal()` closes after, `->position('before'|'after')` places it around Cancel/Submit.
- Color, icon and visibility come from the shared `HasColor`, `HasIcons`, `HasVisibility` concerns.
- On actions, `->label()`, `->icon()`, `->color()`, `->tooltip()` and `->size()` each accept `string|Closure|null`,
  so they can be computed per row — the Closure receives the record: `->color(fn ($record) => $record->isPaid() ? 'success' : 'danger')`.
  This differs from table columns, where `->color()` is static and per-state colors use `->colorUsing()` / `->colors()`.
- `->action(fn ($record) => …)` runs the action; the callback receives the current record.

### Modals

`Modal`, `ConfirmationDialog`, `SlideOver` and `Wizard`. Prefer attaching a modal to an action over
building bespoke modal state.

### Notifications

`Notification` is an immutable value object dispatched through a driver (session, livewire, flasher, null),
selected by `wire-core.notifications.default`.

### Infolists

Read-only counterpart of forms. `Infolist::make()->schema([...])` with entries: `TextEntry`, `IconEntry`,
`ImageEntry`, `ColorEntry`, `KeyValueEntry`, `RepeatableEntry`. Integrates with `ViewAction->infolist()`.

### Widgets

`StatsOverviewWidget` / `Stat`, `ChartWidget`, `BarChartWidget`, `TableWidget`, `CustomWidget`.

### Icons & colors

Icons resolve by name through the `IconManager` (bundled Heroicons solid + `outline:` prefix). Use
`list-icons` to find a name. Colors and sizes are semantic tokens owned by the Foundation palette.

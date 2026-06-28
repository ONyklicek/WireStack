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

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

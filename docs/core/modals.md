# Modals

Modal system for confirmation dialogs, slide-overs, and multi-step wizards. Lives inside `wire-core` as a separate module, prepared for future extraction (see [ADR 0006](../decisions/0006-modular-core-extraction-strategy.md)).

## Modal Types

| Class | Description |
|-------|-------------|
| `Modal` | Standard centered modal |
| `ConfirmationDialog` | Modal with confirm/cancel buttons |
| `SlideOver` | Panel sliding from the right |
| `Wizard` | Multi-step wizard with step navigation |

## Confirmation Dialog

Most common use — triggered from Actions:

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete this record?')
    ->modalDescription('This action cannot be undone.')
    ->modalIcon('trash', 'danger')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('Cancel')
    ->action(fn ($record) => $record->delete());
```

## Slide-Over

```php
Action::make('details')
    ->slideOver()
    ->stickyHeader()
    ->stickyFooter()
    ->modalMaxHeight('60vh');
```

## Modal Appearance

```php
Action::make('edit')
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl
    ->modalAlignment('center')       // center, start
    ->closeModalOnClickAway()
    ->closeModalOnEscape()
    ->slideOverOnMobile()            // slide-over on mobile, modal on desktop
    ->fullScreenOnMobile();          // full screen on mobile
```

## Footer Actions

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

Action::make('edit')
    ->modalFooterActions([
        ModalFooterAction::make('save')
            ->label('Save')
            ->color('primary')
            ->submit(),
        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),
    ]);
```

## Multi-Step Wizard

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('info')
            ->label('Basic Info')
            ->description('Enter details')
            ->icon('user')
            ->fields([...]),

        ModalStep::make('settings')
            ->label('Settings')
            ->fields([...]),

        ModalStep::make('review')
            ->label('Review')
            ->fields([...]),
    ])
    ->action(fn ($record, $data) => $record->update($data));
```

## Blade Components

```blade
<x-wire-modals::modal />
<x-wire-modals::confirmation />
```

## Module Dependencies

Modals depends only on Foundation. It does not depend on Actions or Notifications (see [ADR 0007](../decisions/0007-internal-module-dependencies.md)).

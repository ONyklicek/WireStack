# Modals

Modal system for confirmation dialogs, slide-overs, and multi-step wizards.

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

Panel slides in from the right:

```php
Action::make('details')
    ->slideOver()
    ->stickyHeader()
    ->stickyFooter()
    ->modalMaxHeight('60vh');
```

## Modal Configuration

```php
Action::make('edit')
    // Size
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl

    // Alignment
    ->modalAlignment('center')       // center, start

    // Close behavior
    ->closeModalOnClickAway()
    ->closeModalOnEscape()

    // Mobile adaptations
    ->slideOverOnMobile()            // slide-over on mobile, modal on desktop
    ->fullScreenOnMobile();          // full screen on mobile
```

## Modal Object

For advanced use cases, the `Modal` class provides a standalone configuration object:

```php
use NyonCode\WireCore\Modals\Modal;

$modal = Modal::make()
    ->icon('user', 'primary')
    ->color('primary')
    ->fullScreenOnMobile()
    ->mobileWidth('full');

$modal->getIcon();             // 'user'
$modal->getIconColor();        // 'primary'
$modal->getColor();            // 'primary'
$modal->isFullScreenOnMobile(); // true
$modal->toArray();             // serialized config
```

## Footer Actions

Custom buttons in the modal footer:

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

Action::make('edit')
    ->form([...])
    ->modalFooterActions([
        ModalFooterAction::make('save')
            ->label('Save')
            ->color('primary')
            ->submit(),

        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),

        ModalFooterAction::make('cancel')
            ->label('Cancel')
            ->color('gray')
            ->outlined()
            ->close(),                       // closes the modal
    ]);
```

## Multi-Step Wizard

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('info')
            ->label('Basic Info')
            ->description('Enter user details')
            ->icon('user')
            ->fields([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),

        ModalStep::make('settings')
            ->label('Settings')
            ->fields([
                Select::make('role')->options([...]),
                Toggle::make('active'),
            ]),

        ModalStep::make('review')
            ->label('Review')
            ->fields([
                Placeholder::make('summary')
                    ->content(fn ($data) => "Creating {$data['name']}"),
            ]),
    ])
    ->action(fn ($record, $data) => User::create($data));
```

Steps show a progress indicator and allow forward/backward navigation. Each step validates its own fields before advancing.

## Halt Modal

`ActionHalt` creates a secondary confirmation modal mid-execution:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->heading('Warnings Detected')
                ->body('There are unresolved warnings. Continue anyway?')
                ->icon('exclamation', 'warning')
                ->submitLabel('Continue')
                ->cancelLabel('Cancel')
                ->width('md');
        }
    })
    ->action(fn ($record) => $record->process());
```

### ActionHalt API

```php
->heading(string $heading)
->body(string $body)
->icon(string $icon, ?string $color)
->submitLabel(string $label)
->cancelLabel(string $label)
->width(string $width)
->validation(array $rules)          // validate form data before continue
```

## Blade Components

```blade
<x-wire-modals::modal />
<x-wire-modals::confirmation />
```

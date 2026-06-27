---
order: 40
---

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
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl, 6xl, 7xl, full

    // Close behavior
    ->closeModalOnClickAway()
    ->closeModalOnEscape()

    // Mobile adaptations
    ->slideOverOnMobile()            // slide-over on mobile, modal on desktop
    ->fullScreenOnMobile();          // full screen on mobile
```

## Modal Config Objects

Instead of the fluent `->modal*()` setters, an action can be configured from a
declarative modal object. Pass any `ModalContract` — `Modal`, `SlideOver`,
`ConfirmationDialog`, or `Wizard` — to `->modal()`:

```php
use NyonCode\WireCore\Modals\ConfirmationDialog;
use NyonCode\WireCore\Modals\Modal;
use NyonCode\WireCore\Modals\SlideOver;
use NyonCode\WireCore\Modals\Wizard;

// Centered dialog
Action::make('edit')->modal(
    Modal::make()
        ->heading('Edit record')
        ->description('Update the details below')
        ->width('lg')
        ->icon('pencil', 'primary')
);

// Slide-over panel (->mobileOnly() = slide-over on mobile, modal on desktop)
Action::make('view')->modal(
    SlideOver::make()->heading('Details')->width('xl')
);

// Confirmation dialog — with presets (delete / makeDanger / makeWarning / makeInfo)
Action::make('delete')->modal(
    ConfirmationDialog::delete('User')
);

// Multi-step wizard (see below)
Action::make('create')->modal(
    Wizard::make()->heading('Create user')->steps([/* ModalStep::make(...) */])
);
```

The config object's values are translated into the action's modal state and
rendered through the same runtime as the fluent setters — there is a single
canonical modal owner, so both styles behave identically.

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
            ->submitsForm(),

        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),

        ModalFooterAction::make('cancel')
            ->label('Cancel')
            ->color('gray')
            ->outlined()
            ->closesModal(),                 // closes the modal
    ]);
```

## Multi-Step Wizard

Give an action multiple steps with `->steps([...])` (or a `Wizard` object via
`->modal()`). The modal renders a step indicator with **Back / Next / Submit**
navigation; each step validates before advancing, data is shared across all
steps, and the final submit re-validates every step.

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('Basic Info')
            ->description('Enter user details')
            ->icon('user')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->validation(['name' => 'required|min:2']),    // extra per-step rules

        ModalStep::make('Settings')
            ->schema([
                Select::make('role')->options([...]),
                Toggle::make('active'),
            ])
            ->afterValidation(fn (array $data) => logger('step 2 passed', $data)),

        ModalStep::make('Review')
            ->before(fn (array $data) => ['summary' => "Creating {$data['name']}"]) // pre-fill
            ->schema([
                Placeholder::make('summary')
                    ->content(fn ($data) => $data['summary'] ?? ''),
            ]),
    ])
    ->action(fn ($record, $data) => User::create($data));
```

Each step writes to the same form-data bag, so values entered earlier persist as
the user moves back and forth. Validation on **Next** runs the step's field rules
(via the form runtime), then any `->validation()` rules, then the
`afterValidation` hook; the next step's `before` hook can return an array to
pre-fill it. **Submit** re-validates every step cumulatively (the
`afterValidation` hooks are not re-run, so they never fire twice).

### ModalStep API

```php
ModalStep::make(string $label)
    ->description(?string $description)
    ->icon(string|Icon|null $icon)
    ->schema(array|Closure $fields)
    ->validation(array|Closure $rules)         // extra rules, keyed by field name
    ->validationMessages(?array $messages)
    ->afterValidation(Closure $callback)       // runs after the step validates
    ->before(Closure $callback)                // runs before the step shows; return array to pre-fill
```

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

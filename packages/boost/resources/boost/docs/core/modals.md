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
    ->slideOverOnMobile()            // bottom-sheet on mobile, dialog on desktop
    ->fullScreenOnMobile()           // full screen on mobile
    ->mobileBreakpoint('md');        // where the sheet kicks in (sm|md|lg)
```

Below the mobile breakpoint, `slideOverOnMobile()` renders the form modal as a
**bottom-sheet** that slides up from the bottom edge, and `fullScreenOnMobile()`
fills the viewport; both scroll the body *inside* the panel so the footer buttons
stay visible, and both keep the centered dialog unchanged on desktop. Sheets add
safe-area padding, a drag-to-dismiss grabber and a focus trap automatically.

The breakpoint defaults to the global `wire-core.mobile.breakpoint` (`sm`, i.e.
`< 640px`) and can be raised per action with `->mobileBreakpoint('md')` (`< 768px`,
includes small tablets) or `'lg'` (`< 1024px`). See
[Configuration → Mobile](../configuration.md#mobile) for the global default.

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
    Modal::make() // [tl! focus:start]
        ->heading('Edit record')
        ->description('Update the details below')
        ->width('lg')
        ->icon('pencil', 'primary') // [tl! focus:end]
);

// Slide-over panel (->mobileOnly() = slide-over on mobile, modal on desktop)
Action::make('view')->modal(
    SlideOver::make()->heading('Details')->width('xl') // [tl! focus]
);

// Confirmation dialog — with presets (delete / makeDanger / makeWarning / makeInfo)
Action::make('delete')->modal(
    ConfirmationDialog::delete('User') // [tl! focus]
);

// Multi-step wizard (see below)
Action::make('create')->modal(
    Wizard::make()->heading('Create user')->steps([/* ModalStep::make(...) */]) // [tl! focus]
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

        ModalFooterAction::make('reset')
            ->requiresConfirmation()         // asks before running (native wire:confirm)
            ->confirm('Really reset the form?') // …or with a custom message
            ->action(fn ($set) => $set('name', '')),
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

### Customising the navigation labels

The wizard's **Back**, **Next** and the submit-in-progress **Saving…** labels are
configurable and fall back to translatable defaults
(`wire-core::actions.{wizard_previous,wizard_next,submit_saving}`):

```php
Action::make('create')
    ->steps([/* ... */])
    ->modalPreviousActionLabel('Back')
    ->modalNextActionLabel('Continue')
    ->modalSubmitActionLabel('Create user')
    ->modalSavingLabel('Creating…');
```

[`modalFooterActions()`](../forms/reactive-fields.md#modal-footer-actions) render in
a wizard footer too, alongside Back / Next / Submit.

### Building a step from earlier data

Pass a Closure to `->schema()` to build a step's fields from the values entered in
previous steps. The Closure receives the accumulated form-data bag, so a later step
can adapt to an earlier choice. This works for row, bulk **and** header actions —
a header action carries no record, so its step Closures still receive the live
form-data bag (not `null`):

```php
HeaderAction::make('create')
    ->steps([
        ModalStep::make('Type')
            ->schema([
                Select::make('kind')
                    ->options(['business' => 'Business', 'person' => 'Person']),
            ]),

        ModalStep::make('Details')
            // $data holds everything entered so far (here: 'kind' from step 1).
            ->schema(fn (array $data) => [
                TextInput::make('name')->required(),
                ...($data['kind'] === 'business'
                    ? [TextInput::make('vat_id')->required()]
                    : [TextInput::make('birth_date')]),
            ]),
    ])
    ->action(fn (array $data) => Customer::create($data));
```

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

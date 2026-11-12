---
order: 30
summary: Confirmation dialogs, slide-overs and multi-step wizards — the surfaces an action opens, and what each does with focus, state and a submit.
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
    ->slideOverOnMobile()            // bottom sheet below the breakpoint (the default for dialogs) [tl! focus:start]
    ->fullScreenOnMobile()           // full screen below the breakpoint
    ->dialogOnMobile()               // keep the floating dialog below the breakpoint [tl! focus:end]
    ->mobileBreakpoint('md');        // where the mobile presentation kicks in (sm|md|lg)
```

See [On A Phone](#on-a-phone) for how the mobile presentation is chosen, and
what every dialog does about scrolling, focus and Escape.

A `Modal` built directly takes one more: `->mobileWidth(string $width)` sets the
width the panel uses below that breakpoint, for the case where neither a sheet
nor a full screen is what you want.

A **confirmation with nothing to confirm** — a message the user can only
acknowledge — is `->informative()`: it drops the submit button and relabels the
remaining one as *Close*, so a dialog that asks nothing does not offer to do
anything either.

## On A Phone

Below the mobile breakpoint a dialog changes how it presents; above it every
variant is the same centered dialog.

**The presentation is resolved per modal, in this order:**

1. **The modal says.** `->slideOverOnMobile()` → a bottom sheet;
   `->fullScreenOnMobile()` → the whole screen; `->dialogOnMobile()` → the
   floating card it is on a desktop. Setting either flag, to `true` *or* `false`,
   counts as the modal speaking — `dialogOnMobile()` is both set to `false`. When
   both are on, the sheet wins.
2. **The config.** A modal that set neither follows `wire-core.modals.mobile`:
   `'sheet'` (default), `'full-screen'` or `'dialog'`. An unknown value is read
   as `'sheet'`.

So **every dialog is a bottom sheet on a phone unless something says otherwise**
— a confirmation included. A desktop **slide-over** is the exception: the config
default is about dialogs, and a side panel is already edge to edge on a phone,
so a slide-over becomes a sheet only when it calls `->slideOverOnMobile()`
itself.

```php
// config/wire-core.php
'modals' => [
    'mobile' => env('WIRE_MODAL_MOBILE', 'sheet'), // 'sheet' | 'full-screen' | 'dialog'
],
```

```php
Action::make('pickColour')
    ->form([/* … */])
    ->dialogOnMobile()               // a small dialog stays a card on a phone

Action::make('composeEmail')
    ->form([/* … */])
    ->fullScreenOnMobile()           // a long form takes the whole screen
```

The breakpoint is `->mobileBreakpoint()`, else `wire-core.mobile.breakpoint`
(`sm`, `< 640px`); `'md'` (`< 768px`) and `'lg'` (`< 1024px`) widen it. See
[Configuration → Mobile](../start/configuration.md#mobile).

**What the sheet and the full screen do:**

- **The body scrolls inside the panel**, so the footer buttons stay on screen.
- **A sheet moves like an iOS sheet.** Every sheet — a dialog, a select, a
  picker, a filter panel — shares one gesture (`x-sheet-dismiss`):
  - it is grabbed by the grabber *or* anywhere in its top 56px;
  - pulled down past ~90px, or flicked (40px at more than half a pixel per
    millisecond), it closes; a shorter, slower pull springs back;
  - a list opens at the **medium** height (about 56 % of the screen) when it is
    taller than that, and is pulled up to the **large** one (92 %); pulled down
    from large it settles on medium first. A dialog has the large height only;
  - on a list scrolled to its top, a drag down moves the sheet instead of the
    list — the hand-off iOS does;
  - past the largest height it rubber-bands, and the backdrop fades as the
    sheet goes down;
  - with the keyboard up, the sheet is lifted above it (`visualViewport`).
- **Nothing sits in the rounded corners.** A sheet keeps at least 1rem below its
  last row even where the safe area is zero, and a list inside one is inset from
  the screen edges with rounded rows.
- **Heights use `dvh`**, not `vh`: on a phone `vh` is the viewport with the
  browser's toolbars hidden, and a `85vh` sheet put its own footer under
  Safari's bottom bar. A sheet is at most `92dvh` high.
- **Safe areas are padded.** The footer of an edge-to-edge variant keeps its
  buttons above the home indicator (`env(safe-area-inset-bottom)`), and a
  full-screen panel keeps clear of the notch. The admin layouts set
  `viewport-fit=cover` so those insets are non-zero; a layout of your own needs
  `<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">`
  for the same effect.

### Scroll Lock

The page behind **every** open modal stays put — dialog, sheet, slide-over,
confirmation. The lock sits on `<html>` (iOS Safari ignores `overflow: hidden`
on `<body>` and keeps scrolling the page under a finger), and the scrollbar's
width is put back as padding so a desktop page does not jump sideways.

Locks are **counted per owner**: a modal stacked on a modal, or a sheet over a
dialog, releases the page only when the last one closes. A lock is also
released when its element leaves the page — an action modal is usually removed
by the server's re-render rather than closed, and a lock that waited for
`show = false` would outlive it.

Two ways to use the same lock for an overlay of your own:

```blade
{{-- lock while the expression is truthy, and when the element is removed --}}
<div x-data="{ open: false }">
    <div x-show="open" x-scroll-lock="open">…</div>
</div>
```

```js
// imperative — the admin drawer uses this
window.wireScrollLock.lock(drawerElement)
window.wireScrollLock.unlock(drawerElement)
```

The directive is registered with wire-core's Alpine bundle; the attribute
`data-wire-scroll-locked` is on `<html>` while any lock is held.

### Focus And Keyboard

- **Where focus lands when a dialog opens:** an `[autofocus]` field if the
  dialog has one; on a touch screen the dialog itself, because focusing a field
  there raises the keyboard over a sheet the user has not read yet; otherwise
  the first control that is **not** the close button. A confirmation opens on
  **Cancel**, so Enter does not confirm a delete by accident. Focus stays
  trapped in the dialog while it is open.
- **Escape closes the dialog only when nothing inside used it.** An open
  dropdown, select or date picker inside the dialog takes the first Escape and
  closes itself; the dialog stays. The next Escape closes the dialog (subject to
  `->closeModalOnEscape()`).
- **Labelled for assistive technology.** Each dialog has its own title and
  description ids, wired to `aria-labelledby` and (when it has a description)
  `aria-describedby`, so two
  stacked dialogs never point at each other's heading.

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
            $action->halt()                                    // [tl! focus:start]
                ->heading('Warnings Detected')
                ->description('There are unresolved warnings. Continue anyway?')
                ->icon('exclamation', 'warning')
                ->submitLabel('Continue')
                ->cancelLabel('Cancel')
                ->width('md');                                 // [tl! focus:end]
        }
    })
    ->action(fn ($record) => $record->process());
```

### ActionHalt API

A halt is a modal, so it takes the modal vocabulary — the same `heading()` and
`description()` the classes on this page use:

```php
->heading(string|Closure|null $heading)
->description(string|Closure|null $description)
->icon(string|Icon|null $icon, string|Color|null $color = null)
->submitLabel(?string $label)
->cancelLabel(?string $label)
->width(string|ModalWidth $width)
->closeOnClickAway(bool $close = true)
->closeOnEscape(bool $close = true)
->validation(array $rules, ?array $messages = null, ?array $attributes = null)
```

The full surface, and what a halt does with `before()` hooks on the confirmed
pass, is in [Lifecycle And Queues](actions/lifecycle.md#the-halt-api).

## Blade Components

```blade
<x-wire-modals::modal />
<x-wire-modals::confirmation />
```

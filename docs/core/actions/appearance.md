---
order: 40
summary: "The same action as a full button, an icon, a link or a barely-there row affordance — what changes is the drawing, never the behaviour."
---

# Buttons And Appearance

One action, several shapes: a full button in a header, an icon in a dense row, a
plain link to somewhere else, a keyboard shortcut with no button at all. What
changes here is how an action is drawn, never what it does — and the colour,
size and icon vocabulary is the canonical one every other surface in the
framework resolves through.

## Icon Button

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()          // renders as icon-only button
    ->tooltip('Edit record');

// Or hide just the label
Action::make('edit')
    ->icon('pencil')
    ->hideLabel();          // onlyIcon() is the same call under a name that reads better here
```

`iconButton()` and `hideLabel()` are not the same thing: the first also gives the
button the square icon-button shape and its own colour resolver, the second only
takes the words away. `onlyIcon()` is an alias of `hideLabel(true)`, kept because
that is what the call means at a row-action call site.

## URL Actions

```php
Action::make('view')
    ->url(fn ($record) => route('users.show', $record), openInNewTab: true);

// String URL
Action::make('docs')
    ->url('/docs', openInNewTab: true);
```

## Keyboard Shortcuts

```php
Action::make('save')->keyboardShortcut('mod+s');
Action::make('delete')->keyboardShortcut('Delete');
```

Uses Alpine.js `@keydown` under the hood.

## Outlined & Sizing

```php
Action::make('cancel')
    ->outlined()                    // outline variant, instead of the default solid fill
    ->color('gray')
    ->size('sm');                   // xs, sm, md, lg
```

## Quiet Row Actions

By default a table's row actions render as solid, always-colored buttons. Set the
table's action style to `quiet` for a calmer, more professional look — actions
rest as neutral text and reveal their color only on hover or keyboard focus, so a
row full of actions stops competing with the data.

```php
$table->actionsStyle('quiet'); // default is 'solid'
```

Behaviour of the quiet style:

- Non-destructive actions rest neutral gray and gain their `->color()` on hover/focus.
- **Destructive actions stay legible at rest** (red text), because touch devices have
  no hover — a `DeleteAction` still reads as dangerous without interaction.
- Every action keeps a visible keyboard focus ring.

Keep a single action prominent by opting it back into the solid fill with `->solid()`:

```php
$table
    ->actions([
        Action::make('view')->icon('outline:eye'),
        Action::make('edit')->icon('pencil')->color('primary'),
        Action::make('approve')->icon('check')->color('success')->solid(), // stays a filled button
        DeleteAction::make(),                                              // legible red at rest
    ])
    ->actionsStyle('quiet');
```

The quiet style is opt-in; existing tables are unaffected. `->solid()` and
`->outlined()` remain available as per-action overrides.

## Extra Attributes

```php
Action::make('custom')
    ->extraAttributes([
        'data-testid' => 'custom-action',
        'x-on:click' => 'console.log("clicked")',
    ]);
```

## Related

- [Actions](index.md) — the classes being drawn
- [Foundation: Colors](../foundation/colors.md) and [Icons](../foundation/icons.md) — the vocabulary these settings speak
- [Quiet row actions](#quiet-row-actions) — the dense-table variant, above
- [The Gesture Layer](../../table/gestures.md) — keyboard reach over a whole table

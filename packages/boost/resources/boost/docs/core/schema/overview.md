---
order: 1
summary: "The shared vocabulary for arranging content: one ordered array of components that forms, infolists and action modals all consume."
---

# Schema

A **schema** is an ordered array of components passed to `->schema([...])`. It is
the shared vocabulary for arranging content, and the same components render
across surfaces — forms, infolists, and action modals all consume a schema.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Section;

Section::make('profile')
    ->label('Profile')
    ->schema([
        Grid::make()->columns(2)->schema([
            TextInput::make('first_name'),
            TextInput::make('last_name'),
        ]),
    ])
```

## How it works

A schema is a **tree of components**. Two kinds make up that tree:

- **Fields** carry a value and a state path — `TextInput`, `Select`, `Toggle`, …
  They bind to your model/state and participate in validation. See the
  [Forms → Fields](../../forms/fields/index.md) reference.
- **Layout & schema components** carry no state of their own; they arrange their
  children. `Grid`, `Section`, `Tabs`, `Wizard`, and friends each take their own
  `->schema([...])`, so layouts nest arbitrarily deep.

At render time the host walks the tree depth-first: every component resolves its
own configuration (labels, visibility, columns) and renders its Blade view,
recursing into child schemas. Because layout components hold no value, they can
be added, removed, or reordered freely without touching your data — only fields
map to state.

All schema components live under `NyonCode\WireCore\Foundation\Schema` and extend
the shared `LayoutComponent` base, which is why the identical `Grid` or `Section`
works in a form, an infolist, or a modal.

## Column spanning

Any child of a column-based layout (`Grid`, `Section`, `Fieldset`, `Tab`, `Step`)
controls its own width:

```php
TextInput::make('bio')->columnSpan(2);      // span two columns
TextInput::make('notes')->columnSpanFull(); // span the full row
```

## Common Layout API

Every layout component — `Grid`, `Flex`, `Section`, `Fieldset`, `Tab`, `Step`,
`Tabs`, `Wizard` — extends the same `LayoutComponent` base, so this surface is
the same on all of them and the per-component pages list only what each one
adds.

`Component::make(?string $name = null)` builds one. The name is optional and is
*not* a state path: a layout holds no value, so the name only names the thing.

```php
->label(string|Closure|null $label)      // heading text; falls back to Str::headline() of the name
->hiddenLabel(bool $condition = true)    // keep the component, draw no heading
->schema(array $components)              // the children, in render order
->statePath(?string $path)               // re-root the state path for everything beneath
->columnSpan(int|string $span)           // 2|3|4|'full' — how much of the PARENT grid this takes
->columnSpanFull()                       // shorthand for 'full'
->visible(bool|Closure $condition = true)
->hidden(bool|Closure $condition = true)
->visibleWhen(string $field, mixed $value = true)   // shown while another field equals $value
->hiddenWhen(string $field, mixed $value = true)
->disabled(bool|Closure $condition = true)          // disables every field beneath it
->disabledWhen(string $field, mixed $value = true)
->livewire(mixed $livewire)              // the host; set for you when a form prepares its children
->getName(): string
->getLabel(): ?string
->getSchema(): array
->getColumnSpan(): int|string|null
->isVisible(): bool
->isHidden(): bool
->isDisabled(): bool
```

Three of these are worth a sentence each, because they are the ones people meet
as surprises:

- **`columnSpan()` is about the parent, not the child.** It says how much of the
  grid *containing* this component it takes up. It understands `2`, `3`, `4` and
  `'full'` and nothing else — `columnSpan(5)` silently means "one column".
- **`visible()` takes a closure and is evaluated on every render**, so a layout
  can appear and disappear as the form's state changes. `visibleWhen('type',
  'company')` is the same thing written for the common case.
- **`disabled()` cascades.** Disabling a section disables every field inside it,
  which is one condition instead of the same one repeated on each field.

## Layout components

| Component | Purpose |
|-----------|---------|
| [Grid](layout/grid.md) | Responsive multi-column layout |
| [Flex](layout/flex.md) | Arrange children on a single horizontal (flexbox) axis |
| [Section](layout/section.md) | Group components under a heading, optionally collapsible |
| [Fieldset](layout/fieldset.md) | Group related components with a bordered legend |
| [Tabs](layout/tabs.md) | Client-side tabbed panels (all panels validate together) |
| [Wizard](layout/wizard.md) | Client-side multi-step layout with a step indicator |

## Prime components

Static, non-input components that display content:

| Component | Purpose |
|-----------|---------|
| [Callout](callout.md) | Soft, colored notice box with heading and icon |
| [Empty State](empty-state.md) | Centered placeholder shown when there is nothing to display |

## Where schemas are used

Because these components live in core `Foundation\Schema`, they are consumed by
more than forms:

- **Forms** build their body from a schema, and from these classes directly. The
  thin `NyonCode\WireForms\Components\Layout\*` subclasses that used to swap in
  form-specific markup were removed in 2.0: their copies of the views had fallen
  behind the originals, so a form section rendered less than an infolist section
  built from the same class.
- **Infolists** reuse the same layout vocabulary for read-only detail views.
- **Action modals** use [Wizard](layout/wizard.md) for multi-step flows — see
  [Modals → Multi-Step Wizard](../modals.md#multi-step-wizard).

## Related

- [Forms](../../forms/overview.md) — the surface that builds its body from a schema
- [Infolists](../infolists/index.md) — the same layouts, read-only
- [Fields](../../forms/fields/index.md) — the components that do carry state
- [Modals](../actions/modals.md) — action modals, which consume a schema too

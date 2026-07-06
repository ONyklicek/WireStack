---
order: 1
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

- **Forms** build their body from a schema. `Grid`, `Section`, and `Fieldset`
  also have thin `NyonCode\WireForms\Components\Layout\*` aliases (deprecated in
  v2.0) that only swap in form-specific markup; every other schema component is
  used directly.
- **Infolists** reuse the same layout vocabulary for read-only detail views.
- **Action modals** use [Wizard](layout/wizard.md) for multi-step flows — see
  [Modals → Multi-Step Wizard](../modals.md#multi-step-wizard).

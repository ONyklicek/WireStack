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

**A span is resolved against the grid it lands in, at every width.** A grid is
responsive — `columns(3)` is one column on a phone and three on a desktop — so a
span is not one class but a ladder: `columnSpan(3)` in a three-column `Grid` is
`md:col-span-3`, and the same span in a `Fieldset` (which reaches two columns
already at `sm`) is `sm:col-span-2 md:col-span-3`. You write the number; the
breakpoints follow the grid.

**A span can never be wider than its grid.** Asking for more is not a clipped
tile — CSS Grid answers by *adding* the missing column, which re-flows the whole
layout and squeezes every other child into the remainder. A span wider than the
declared count is drawn as the full width of the grid instead, so
`columnSpan(4)` in a two-column section is two columns, everywhere.

**A grid tells its children which grid they are in.** A component never asks
where it sits — it is told on the way in, and resolves its own ladder from what
it was told. Every shipped layout does this for you, so it matters only when you
write a layout component of your own: hand the same column count to
`ResponsiveGrid::cols()` for the grid and to each child's `inGridOf()`, and the
spans inside it step with the grid instead of beside it.

```php
@php
    use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

    $columns = $layout->getColumns();
@endphp

<div class="grid gap-4 {{ ResponsiveGrid::cols($columns) }}">
    @foreach ($layout->getSchema() as $component)
        @if ($component->isVisible())
            {{ $component->inGridOf($columns) }} {{-- [tl! focus] --}}
        @endif
    @endforeach
</div>
```

A child nobody told assumes a grid exactly as wide as the span it asked for.
That is the narrowest assumption that can never invent a column — and it is also
not the grid you drew, so a `columnSpan(2)` in your own three-column layout
would reflow at the wrong width and nothing would report it.

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
->inGridOf(int|array $columns)           // the grid this is drawn in; a layout tells each of its children
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
  grid *containing* this component it takes up, capped by what that grid has:
  `columnSpan(5)` in a four-column grid is four columns, and in a two-column one
  it is two. `columnSpanFull()` is the row, whatever the row turns out to be.
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

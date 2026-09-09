---
order: 50
summary: "The standalone `<x-wire::*>` components — buttons, badges, icons and the layout ones — usable in any Blade view, with no schema around them."
---

# Blade Components

Everything the framework draws is also available as a plain Blade component. A
view that is not a form, not a table and not an infolist can still use the same
button, badge, icon and layout — which is what keeps an application's own screens
from looking like a different product.

## Blade Components

Foundation provides base components under the `wire::` namespace. `color` and
`size` speak the same vocabulary everywhere — `primary`, `danger`, `success`,
`warning`, `info` and the hue names beside them — because every one of these
resolves through the canonical owners (`HasColor`, `HasSize`) rather than
carrying a palette of its own. `outlined` swaps the solid fill for a border.

```blade
{{-- Icon --}}
<x-wire::icon name="check" />

{{-- Badge --}}
<x-wire::badge color="success">Active</x-wire::badge>

{{-- Button --}}
<x-wire::button color="primary" size="sm">Save</x-wire::button>

{{-- Dropdown --}}
<x-wire::dropdown>
    <x-slot:trigger>Options</x-slot:trigger>
    <x-wire::dropdown.item>Edit</x-wire::dropdown.item>
    <x-wire::dropdown.item>Delete</x-wire::dropdown.item>
</x-wire::dropdown>

{{-- A file: the picture when there is one, its extension over its family's colour otherwise --}}  {{-- [tl! focus:start] --}}
<span class="flex h-10 w-10 overflow-hidden rounded-lg">
    <x-wire::file-thumb :name="$file->name" :mime="$file->mime_type" :url="$file->previewUrl()" size="md" />
</span>                                                          {{-- [tl! focus:end] --}}
```

A **menu row** is `<x-wire::menu-item>` — a link, or a button that submits the
form around it, drawn the way the framework's own menus draw one:

```blade
<x-wire::menu-item :href="route('profile')" icon="outline:user-circle" wire:navigate>
    Profile
</x-wire::menu-item>
```

It is the core component; `<x-wire-admin::menu-item>` is the same row inside the
[admin shell's](../../admin/layout.md) user menu, so anything you put there looks
like what is already in it.

`file-thumb` fills whatever box it is put in — the same component is a 32-pixel
row thumbnail and a full-bleed grid tile — and `size` (`sm`, `md`, `lg`) scales
what is drawn inside it. It renders the picture only when the file is an image
**and** a `url` was given; a PDF handed a perfectly good URL still gets the card,
because a PDF drawn through `<img>` is a broken-image icon claiming the file is
damaged.

## Layout Components

A canonical layout vocabulary lives in `NyonCode\WireCore\Foundation\Schema\*` and is shared by both
**forms** and **infolists** (the forms `Layout\*` classes extend the core versions). Use these in any
`->schema([...])` array instead of ad-hoc Blade grids.

| Component | Purpose |
|-----------|---------|
| `Grid` | Responsive column grid |
| `Section` | Titled card with heading/description |
| `Fieldset` | Bordered group with a legend |
| `Flex` | Side-by-side flexbox row that stacks on mobile |
| `Tabs` / `Tab` | Tabbed panels |
| `Wizard` / `Step` | Multi-step layout |
| `Callout` | Soft colored notice box |
| `EmptyState` | Icon + heading + description + actions |

```php
use NyonCode\WireCore\Foundation\Schema\{Grid, Section, Flex, Callout};

Section::make('Team')
    ->description('People with access.')
    ->schema([
        // Int reflow, or a Filament-style per-breakpoint map.
        Grid::make()->columns(['default' => 1, 'md' => 2, 'lg' => 3])->schema([...]),
    ]);

// Flex: control distribution, alignment, spacing, wrap and child growth.
Flex::make()->from('md')->justify('between')->align('center')->gap(6)->wrap()->grow(false)->schema([...]);

// Callout — color hues delegate to the canonical alert palette.
Callout::make()->warning()->heading('Heads up')->icon('exclamation-triangle')->dismissible()
    ->content('Something worth noticing.');
```

`Callout` is the shared owner of the notice surface; the forms `Alert` field is its field-style alias.
Column counts (`Grid`, `CheckboxList`, `Section`, …) accept an int **or** a per-breakpoint map keyed by
`default`/`sm`/`md`/`lg`/`xl`/`2xl`.

### Standalone Blade tags

The same layouts are also exposed as slot-based `wire::` tags for plain Blade views (no schema array):

```blade
<x-wire::callout color="warning" heading="Storage almost full" icon="exclamation-triangle" dismissible>
    You have used 95% of your quota.
</x-wire::callout>

<x-wire::grid :columns="['default' => 1, 'md' => 2, 'lg' => 3]" gap="gap-3">…</x-wire::grid>

<x-wire::flex from="md" justify="between" align="center" :gap="4">…</x-wire::flex>

<x-wire::section heading="Profile" description="Basic info">…</x-wire::section>
<x-wire::fieldset legend="Billing address">…</x-wire::fieldset>

<x-wire::empty-state icon="outline:inbox" heading="No invoices yet" description="They will show up here.">
    <button>New invoice</button> {{-- slot becomes the action row --}}
</x-wire::empty-state>

{{-- Alpine-driven; client-side state only (no per-step validation) --}}
<x-wire::tabs>
    <x-wire::tab label="Profile">…</x-wire::tab>
    <x-wire::tab label="Security">…</x-wire::tab>
</x-wire::tabs>

<x-wire::wizard>
    <x-wire::step label="Account">…</x-wire::step>
    <x-wire::step label="Confirm">…</x-wire::step>
</x-wire::wizard>
```

For validated multi-step flows use action-modal wizards (`HasModal::steps()`) or the form schema `Wizard`
instead — the standalone `<x-wire::tabs>` / `<x-wire::wizard>` only switch panels client-side.

## Related

- [Schema](../schema/overview.md) — the same layouts as declarative components
- [Colors](colors.md) and [Icons](icons.md) — the attributes these accept
- [The Admin Shell](../../admin/overview.md) — a frame built out of these
- [Theming](../../start/theming.md) — overriding the views behind them

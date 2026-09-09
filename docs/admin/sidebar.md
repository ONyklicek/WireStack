---
order: 30
summary: The menu component — on its own or inside the layout — the 64-pixel rail, and what each row becomes when the labels go away.
---

# The Sidebar

The menu is its own component. It reads [`Workspace`](../panels/navigation.md),
links through `ResolvesPageUrls`, and marks the active entry from the route being
rendered — so it needs nothing declared and holds no state of its own.

## The Sidebar On Its Own

An application with its own frame uses the menu without the layout:

```blade
<x-wire-admin::sidebar />
<x-wire-admin::sidebar :linked-only="true" />
```

`linkedOnly` decides what happens to a registered entry this zone does not route.
By default it stays in the menu as a row without a link — visible, not clickable
— which is the honest picture of a half-routed application. With `linkedOnly` it
is left out instead.

Both forms accept an explicit `zone` and `activeKey` when a host has already
resolved them:

```blade
<x-wire-admin::sidebar :zone="$zone" :active-key="$activeKey" />
```

## The Collapsed Menu

The handle in the top bar — or `⌘B` / `Ctrl+B` — narrows the column to a
64-pixel rail. The choice is kept in `localStorage` under `wire-admin.rail`, and
**applied before the page paints**, by the same kind of blocking head script that
decides the theme. That is not a refinement: read from the Alpine store instead,
the first frame of every page is the *other* answer, and because the column
carries `transition-[width]` a collapsed menu opened to 288 pixels, showed every
label, and slid shut over a third of a second — on every load and every
`wire:navigate`.

The shortcut is declined while the caret is in a field, because in a rich-text
editor the same chord is bold.

### What moves, and where it goes

| In the column | In the rail |
| --- | --- |
| The label, beside the icon | The row's `aria-label`, and a tooltip on hover |
| A badge, as a pill after the label | A dot on the corner of the icon, in the same colour |
| Children, as a folded list under their parent | A popover beside the row |
| A group heading | A divider between groups |
| The active entry, tinted | The same tint, plus a mark against the column's edge — a tint is the same shape as a hover, and a rail is nine identical squares |

**A tooltip and a menu are not the same object**, and the difference is the whole
of what makes a collapsed rail readable. This follows the rule SAP Fiori states
for its side navigation: collapsed, "a tooltip with the corresponding label pops
up on-hover", and "subitems appear in a popover".

- An entry with **no children** gets a tooltip — one dark line, no padding of a
  menu, nothing you could mistake for something to open.
- An entry **with children** gets a popover: the parent's name as a heading, and
  its children under it.

Getting that wrong is easy and looks it. An earlier version of this shell gave
every row the same menu-sized card, so pointing at an entry with no children
answered "what is this icon?" with a panel holding a single word.

The popover's child rows are rendered by the **same partial** that draws them
under the parent in the wide menu, so how a child row looks has one definition —
its URL, its active state, its badge colour and its disabled case are not
re-encoded for the panel. A second copy diverges the first time either side is
touched.

**Pointing opens it; brushing past it does not.** A panel waits for the pointer
to rest for about a fifth of a second, so reaching past the menu for the edge of
the page throws nothing over the page. Once one panel is open, moving along the
menu opens the next immediately — the wait is charged per visit to the menu, not
per row. The keyboard reaches them too: tabbing onto a row opens its panel,
`Enter` moves focus into a popover (it is teleported to the end of the document,
so `Tab` alone would step to the next row), and `Escape` closes it and hands
focus back. A dismissal outlives the close until the pointer or focus moves away
— otherwise handing focus back to a row that opens on focus would reopen the
panel, and `Escape` would read as a key that does nothing.

Nothing declares any of this. An entry that carries a badge and children gets the
rail treatment for both:

```php
NavigationItem::make('Invoices')
    ->icon('outline:document-text')
    ->badge(fn (): int => Invoice::where('status', 'overdue')->count(), 'danger')
    ->children([
        NavigationItem::make('All invoices')->url(route('invoices.index')),
        NavigationItem::make('Overdue')->url(route('invoices.index', ['status' => 'overdue'])),
    ]);
```

The colour argument earns more here than it does in the wide menu: in the rail
the badge shrinks to a dot, and the hue is the only part of it still saying
anything.

Two more things the shell settles for you, each of which was once wrong:

- A **collapsible group folded shut** still shows its entries in the rail. The
  heading that would unfold it is hidden there, so a folded group would otherwise
  take its entries out of the menu rather than merely out of sight.
- The rail is a **desktop** shape. Below `lg` the same element is a drawer, so a
  rail collapsed on a laptop does not follow the menu onto a phone.

## Zones

A [zone](../panels/routing.md#zones) is a route group's name prefix, and the shell needs
nothing declared to follow one: the same markup links into `admin` on an admin
page and into `business` on a business one, because the zone comes from the route
being rendered. The palette does the same, which is why its trigger lives in the
frame rather than on a page.

## Related

- [Navigation](../panels/navigation.md) — the entries, groups and badges this draws
- [Routing](../panels/routing.md) — zones, and where an entry's URL comes from
- [The Layout](layout.md) — the frame the sidebar normally sits in


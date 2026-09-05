# ADR 0028: The Optional Panel Shell

## Status

ACCEPTED — 2026-09-05, implemented the same day as `packages/admin`
(`nyoncode/wire-admin`).

Three things the implementation settled that this ADR could only assume:

- **The driver earned its keep on the first run.** The mobile menu did not hide,
  because the open/closed state was driven by a `resize` handler while the
  layout's `lg:` classes are matched on a media query — two sources for one
  boundary. It now listens to the query itself, which also covers a zoom change
  and a rotation. Pest saw correct markup throughout.
- **A workbench check lost its meaning and had to be replaced, not deleted.**
  `verify-resource-routes` asserted `body.min-h-screen` to prove a routed page
  came up inside the application's layout; that class was the hand-written
  workbench frame's. It now asserts the shell's own markers — the sidebar, the
  brand from a slot, and the active entry — which is what the check was always
  trying to say.
- **`Zone` grew a sibling rather than the shell growing a regex.** A menu needs
  the *key* of the route being rendered, and the anchored pattern that reads it
  is `Zone`'s (`livewire.update` contains `wire.`). `Zone::currentKey()` /
  `keyOf()` sit beside `current()` / `of()` over one private matcher.

Originally PROPOSED — 2026-09-05. Requested by the repo owner ("administrace volitelná",
and — asked in the same breath — "kompletní administrace jako samostatný
balíček?"). The answer to that question is yes, and §1 is written around it.
Nothing implemented; this ADR only draws the line the implementation may stand
on. Sequenced in
[`plans/v3-optional-admin-and-module-packages.md`](../plans/v3-optional-admin-and-module-packages.md).

Crosses a line three earlier ADRs held on purpose, and says what may cross it:

- [ADR 0020](0020-application-owner-layer.md) listed "scope creep into a Panel
  Builder" as an explicit risk and mitigated it with **"routing opt-in, no
  shell"**.
- [ADR 0026](0026-registration-seam.md) invariant 6: config may hold a route
  group's arguments, **never a shell**.
- [ADR 0027](0027-routing-zones.md) opens with "no `Panel`, no shell, no layout,
  no branding" and stayed inside it.

None of those three was a decision that a shell is wrong. Each was a decision
that a *registry* must not grow one. This ADR keeps that intact — the shell is
markup in the top package, and the registries below it learn nothing.

## Context

### Everything under a shell already exists; the layout is the hole

Measured against the tree on 2026-09-05, not against the plans:

| A shell needs | Exists as | Where |
| --- | --- | --- |
| what is registered, whatever kind | `Catalog` | `core/Foundation/Registration/Catalog.php` |
| the menu: groups, order, icons, labels, badges, visibility | `Workspace::navigation(?string $zone, bool $linkedOnly)` | `core/Core/Resources/Workspace.php:79` |
| where a key's page is, per zone | `ResolvesPageUrls` → `RegisteredPageUrls::urlFor()` | `panels/Routing/RegisteredPageUrls.php:20` |
| the routes themselves, with zones | `Route::wireResources()`, `ConfiguredRoutes` | `panels/Routing/` |
| the pages | `ListPage`, `CreatePage`, `EditPage`, `ViewPage`, `DashboardPage` | `panels/Resources/Pages/` |
| command palette | `@livewire('wire-global-search')` | `core/GlobalSearch/` |
| toasts, modals, notifications bell | Blade tags | `core/resources/views/` |

What does not exist anywhere in `packages/*` is a **layout**: a page frame with a
sidebar in it. The only one in the repository is the workbench's, hand-written:
`workbench/resources/views/components/layouts/wire.blade.php` (the frame) and
`workbench/resources/views/previews/workspace.blade.php` (the sidebar, ~80 lines
of Blade that read the table above and nothing else).

So the missing piece is not a mechanism. It is the markup every application
retypes — and the reason to ship it is not convenience: `Workspace::navigation()`
returning a group whose label was empty went unnoticed until V2.6 step 1 finally
rendered a menu, because nothing in the repo consumed the arrangement end to end.

### The one real design problem: two URL modes

`v2-progress.md` §4 refused to let the workbench sidebar take its URLs from
`ResourceRoutes::urls()`, and the surviving half of that refusal is a fact about
the shell, not about the registry:

- `/previews/workspace/{key}` **is** the shell — the parameter chooses what
  renders beside the sidebar.
- `wire.{key}.index` is a standalone full-page component in the application's
  own layout, with no sidebar.

A link that took its URL from the router would leave the shell and close the
navigation behind it, so the preview keeps a hand-written key→URL map, and its
comment says so.

A shipped shell has to resolve that, and there is only one way that does not
invent a third URL space: **the shell is the layout the routed pages render
in**. Then `wire.{key}.index` *is* the shell, the sidebar takes every URL from
`ResolvesPageUrls`, and the hand-written map disappears — which closes the
deferred "routed pages in the menu" item as a side effect rather than as a task.

## Decision

### 1. The shell is its own package, `wire-admin`, above `wire-panels` (implemented)

```text
wire-admin -> wire-panels -> wire-table -> wire-forms -> wire-core
wire-sortable -> wire-table
```

Not a directory inside `wire-panels`, and the reason is the same one that made
`wire-panels` a package of its own: **a composer boundary is the only opt-in
nobody can acquire by accident.** Three ADRs (0020, 0026, 0027) held the line
that the owner layer holds no shell, and an application that installs
`wire-panels` for its pages and its routing macro must keep getting exactly that
— not chrome it has to opt out of. `composer require` is that opt-out, and it
needs no config key, no flag and no documentation.

It also keeps the two identities honest as they grow. The shell will carry views,
a stylesheet's worth of markup and at least one Alpine controller (the mobile
sidebar); `wire-panels` ships no asset bundle today and should not grow one to
hold a sidebar. And `wire-admin` is the package that may name every other package
at once — a shell composes tables, forms, infolists, widgets, notifications and
the palette — which is precisely the permission the top of the graph has and the
owner layer deliberately did not want.

The cost is a real one and is paid once: a path repository in the root
`composer.json`, two `phpunit.xml` suites, a `composer test:admin` script, an
entry in `scripts/coverage-floors.json`, a provider in `extra.laravel`, and
monorepo-builder's split. Everything after that is the same as any other package
here.

### 1b. Inside it: Blade with slots. There is no `Panel` object (implemented)

No `Panel::make()->brand()->colors()->navigation()`, no panel registry, no
provider that "registers a panel". A layout component with slots, and a sidebar
component that reads `Workspace`:

```blade
<x-wire-admin::layout :title="$title">
    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>
    <x-slot:user><x-app-user-menu /></x-slot:user>

    {{ $slot }}
</x-wire-admin::layout>
```

The fluent-builder shape is what ADR 0020 named as the risk, and it is the shape
that pulls configuration — branding, colors, auth, tenancy, per-panel middleware
— into a class the registries below would eventually have to know about. Slots
carry the same information and know nothing.

### 2. It is opt-in twice, and neither opt-in is a new mechanism

The package is optional (§1 — nothing requires `wire-admin`, and `wire-panels`
stays installable without it), and inside it the layout is used only when the
application names it: `livewire.component_layout` — Livewire 4's key, not `layout`
(`vendor/livewire/livewire/config/livewire.php:47`) — or `#[Layout]` on a page.
No provider sets it. Installing the package must not be the same act as adopting
its chrome: an application may want the sidebar component inside a layout of its
own, and the layout it renders in stays its decision.

### 3. It reads through the seams that exist, and adds no state

`Workspace::navigation($zone, linkedOnly: …)`, `ResolvesPageUrls`, and the zone
**read once at page render and carried in the snapshot** — ADR 0027's trap:
`Route::currentRouteName()` answers `livewire.update` on every round trip, so a
sidebar that derives its zone per render is right once and wrong forever after,
while looking perfect.

No new registry, no new contract in `core`. If the shell needs something the menu
cannot answer, that is a finding about `Workspace`, and it is fixed there.

### 4. What the shell may own, and what it may not

May: the page frame, the sidebar and its active-entry detection, the mobile
toggle, where the palette / toasts / modal host sit, empty and unrouted entry
states, and publishable views so an application can rewrite any of it.

May not: a URL scheme (0027 — a zone is a route group's `name()`), a
registration path of its own, branding or auth **configuration** (slots instead),
and any knowledge of what kind of thing a catalogue entry is.

### 5. Publishable views are the extension mechanism

`vendor:publish` is what every other package in this repo offers and what
`AI_BLUEPRINT.md` expects. An application that wants a different sidebar
publishes the view. That is deliberately cruder than a builder API, and it is the
crudeness that keeps this from becoming a panel framework by accretion.

## Consequences

### Positive

- The arrangement finally has a shipped consumer, which is the thing that has
  found every navigation defect so far (V2.6 step 1: two empty rows out of three).
- The deferred key→URL duplication closes, because one page kind serves both.
- An application gets an admin by installing a package and naming a layout —
  and gets nothing it did not name.

### Negative / risks

- **Accretion into a panel builder.** Every request for "just one config key" —
  brand color, logo, per-panel middleware — is the first step. The tripwire: the
  moment a *PHP object* is proposed to hold shell configuration, this ADR is
  being amended, and it must be amended explicitly.
- **A design surface with a taste dimension.** The repo's own bar
  (`frontend-design`, Nova/Filament ergonomics) applies, and Pest cannot see it:
  a browser driver is mandatory, per `verify:drivers`.
- **Published views drift** from the package's own. Normal for this repo, and
  the reason the shell must stay small.

## Open questions — answered by building it

1. ~~Does the shell ship a topbar at all?~~ **Yes, and it is nearly empty**: the
   palette trigger, plus `topbar` and `user` slots. The trigger belongs to the
   frame rather than to a page because that is what makes a zone real — the same
   markup links into `admin` on an admin page and into `business` on a business
   one, with nothing declared.
2. ~~Visible-and-dimmed, or `linkedOnly` by default?~~ **Visible and unlinked by
   default**, `:linked-only="true"` to drop them. A registered entry with no page
   is something an application should be able to see it has; hiding it by default
   makes a half-routed catalogue look complete.
3. ~~Dark mode.~~ Every surface in both views carries its `dark:` pair, the way
   the shipped component views do. Nothing new was needed for it.

Still open, and deliberately not answered here:

- **Collapsible groups.** `NavigationGroup` documents why it has no `collapsed()`
  yet — the vocabulary exists twice already, and a third copy would be written
  before anything could use it. The shell is now that consumer, so the next time
  it comes up the answer is a canonical owner first, not a flag on the sidebar.

# ADR 0039: Clusters

## Status

ACCEPTED — 2026-09-27. Requested by the repo owner after a comparison with
Filament's navigation ("filament má lépe řešené … clusters"). Lands beside
`NavigationItem::parent()` and the static `url()` on resources and pages, on
`feat/navigation-urls-and-parents`.

## Context

A settings area is ten screens that belong together: one entry in the menu,
one URL prefix, and a way across between them once you are inside. The
framework had each half and not the whole:

- `NavigationItem::children()` and `parent()` put entries under one menu row,
  but a branch in the sidebar is still ten rows, and says nothing on the page;
- `ConfiguresRoutes::routePrefix()` gives one resource a prefix, written out
  on each member by hand;
- `LinksToRecordPages` draws the sideways navigation between one **record's**
  pages, and nothing drew it between sibling screens;
- `PanelEntry` sends a zone's own path to its first reachable page, and
  nothing did that for a section.

Filament answers this with a *cluster*: a class the members name, which owns
the menu entry, the URL prefix and a sub-navigation on every member page.

## Decision

### 1. A cluster is a `Page`

`NyonCode\WirePanels\Clusters\Cluster extends Pages\Page`. That is not a
shortcut, it is where every part of it already lives: a page is registered in
`config('wire-panels.pages')` or discovered, joins the catalogue, is routed at
its `key()` by `Route::wireResources()`, has a menu entry from the same
statics (`$slug`, `$navigationLabel`, `$navigationIcon`, `$navigationGroup`,
`$navigationSort`, `$permission`) and a `url()`. A second registry for a
second kind of page-like class would be the same list kept twice.

What a cluster changes about being a page is its `index`: it renders nothing
and redirects to the first member the viewer may open, in the order the
sub-navigation draws them — the rule `PanelEntry` follows for a zone, applied
to a section. No member to open is a 403.

### 2. Membership is declared by the member

`Foundation\Routing\Contracts\BelongsToCluster::cluster(): ?string`, in core
because both the menu (core) and the router (panels) read it. A resource
implements it; `Pages\Page` implements it from `protected static ?string
$cluster`. The member names the cluster, not the other way round, for the
reason `parent()` does: the module that ships a settings screen does not own
the application's settings cluster.

A member names a class that must be a registered cluster; anything else is a
`ClusterException` on the first read, not a member that quietly routes at the
root.

### 3. What membership changes

| Surface | Change | Owner |
| --- | --- | --- |
| Menu | the member leaves the grouped menu; the cluster's entry stands for it, lit on every member page | `Workspace::navigation()` (core) |
| Flat menu / palette | unchanged — `items()` keeps every entry | `Workspace::items()` |
| URL | `{cluster prefix}/{member prefix}`; route **names** unchanged | `ResourceRoutes::prefixFor()` |
| Middleware | the cluster's `can:` is added to every member route | `ResourceRoutes::middlewareFor()` |
| Page | the cluster's members as a sub-navigation, above or beside the content | `Clusters\ClusterNavigation`, `pages/partials/cluster-nav` |
| Trail | the cluster is the first crumb | `BelongsToResource::breadcrumbs()`, `Page` |

Route names stay `wire.{key}.{page}` so that everything addressing a page by
key — `urlFor()`, the menu, search results, `X::url()` — keeps working without
knowing the page moved. The prefix is the only thing that changes.

The cluster's permission guards its members for the reason a nested resource
inherits its parent's middleware: a section someone may not enter should not
be enterable through a bookmark.

### 4. The sub-navigation is drawn from the members' own menu entries

Each member already declares a `navigation()` — label, icon, sort, badge,
visibility. The cluster's sub-navigation is those entries, in `sort()` order,
hidden ones dropped, each pointed at the member's `index`. Nothing new is
declared for it, and a member that implements no `ProvidesNavigation` is
routed under the prefix and absent from the sub-navigation, as it would be
from the menu.

The active member is decided by **key**, like the menu, so a member resource
stays lit on its edit page.

`Cluster::$subNavigationPosition` (`SubNavigationPosition::Start` — the
default — `End` or `Top`) says where it is drawn. `Top` is tabs, the same shape
as a record's tabs; `Start`/`End` is a column beside the content from `lg` up
and tabs below it, because a column on a phone is a column nobody sees.

### 5. What it deliberately does not do

- **A cluster inside a cluster.** One level, the rule the menu and nesting
  already follow.
- **A cluster's own content.** Its `index` is a redirect; a section overview
  is a member page like any other, sorted first.
- **Clusters per zone.** A cluster and its members are routed in whichever
  zones route them, by `only`/`except` like everything else.

## Consequences

- A section is one class and one `$cluster` line per member.
- `make:wire-cluster` generates one and `make:wire-page --cluster=` writes a
  member page. A resource joins by implementing `BelongsToCluster` by hand —
  one method — rather than through a fourth placeholder in a stub that
  applications publish and edit.
- A member with a single sibling draws no sub-navigation: one tab is its own
  heading written twice, the rule a record's tabs follow.
- A member moved into a cluster changes URL and keeps every route name; links
  built by key or by `X::url()` follow, hand-written paths do not.

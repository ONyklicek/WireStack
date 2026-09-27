---
order: 45
summary: One section of the admin as one class — a single menu entry, one URL prefix, and the way across between its screens on every one of them.
---

# Clusters

A settings area is ten screens that belong together. In the menu they should be
one entry, in the address bar one prefix, and once you are inside one of them
the other nine should be one click away. A cluster is that section, declared as
one class that its members name.

```text
menu                 URL                             on the page
─────────            ───────────────────────────     ─────────────────────────────
Settings      →      /admin/settings                 redirects to the first member
                     /admin/settings/currencies      Currencies │ Taxes │ Mail
                     /admin/settings/taxes           the same members, Taxes marked
```

## How It Works

A cluster is a [`Page`](pages.md#a-page-of-your-own) — `Clusters\Cluster`
extends it — so it is registered, routed and listed exactly the way a page of
your own is, from the same statics. Everything else follows from one line on each
member: the member names its cluster.

Membership changes five things, each owned by the layer that already did that
job:

| Surface | What changes |
| --- | --- |
| **Menu** | The members leave the grouped menu; the cluster's entry stands for them, and is lit on every member page. `Workspace::items()` — the flat list the command palette searches — keeps them. |
| **URL** | A member is routed at `{cluster prefix}/{its own prefix}`. Its route **name** does not change, so every link built by key — `urlFor()`, `X::url()`, the menu, search results — follows the move. |
| **Middleware** | The cluster's `$permission` (its `can:`) is added to every member route. A section someone may not enter is not enterable through a bookmark either. |
| **Page** | Every member page draws the cluster's members — as a column beside the content or as tabs above it. |
| **Trail** | The cluster is the first crumb: *Settings › Currencies*. |

**The cluster's own address redirects.** It renders nothing: it sends the viewer
to the first member they may open, in the order the members are drawn, skipping
one whose route would refuse them. Every member refusing is a 403; a cluster
nothing names is a 404.

**The members are drawn from their own menu entries.** Each member already
declares a `navigation()` — label, icon, sort, badge, visibility — and the
cluster draws exactly those, in `sort()` order, hidden ones left out. Nothing new
is declared for it, and a member that declares no entry is routed under the
prefix but absent from the navigation, as it would be from the menu. The current
member is found by its **registered key**, so a resource stays marked on its edit
page, not only on its list. A cluster with one visible member draws no navigation:
one tab would be the page's own heading written twice.

**The traps.** A member naming a class that is not a cluster, a cluster that is
not registered, or a cluster inside another cluster is refused with a
`ClusterException` when the routes are registered — never a page quietly routed
at the root, outside the section's permission. And a nested resource sits
wherever its parent sits: its pages are routed under the parent's record, so
under the parent's cluster too.

## Declaring A Cluster

```php
use NyonCode\WirePanels\Clusters\Cluster;
use NyonCode\WirePanels\Enums\SubNavigationPosition;

final class Settings extends Cluster
{
    protected static ?string $navigationIcon = 'outline:cog-6-tooth';   // [tl! focus:start]
    protected static ?string $navigationGroup = 'admin';
    protected static int $navigationSort = 90;
    protected static ?string $permission = 'settings.view';
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;   // [tl! focus:end]
}
```

and register it the way a page is registered — `config('wire-panels.pages')` or a
discovered folder:

```php
// config/wire-panels.php
'pages' => [App\Clusters\Settings::class],
```

`php artisan make:wire-cluster Settings` writes the class into `app/Clusters/`
and says which of the two steps is left.

## Putting A Screen In It

A page names its cluster with a static:

```php
use NyonCode\WirePanels\Pages\Page;

final class Taxes extends Page
{
    protected static string $view = 'livewire.settings.taxes';

    protected static ?string $cluster = Settings::class;   // [tl! focus]

    protected static int $navigationSort = 20;
}
```

A resource implements `BelongsToCluster`, which is one method:

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;

final class CurrencyResource implements BelongsToCluster, DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    public static function cluster(): ?string   // [tl! focus:start]
    {
        return Settings::class;
    }                                            // [tl! focus:end]

    public static function modelClass(): ?string
    {
        return Currency::class;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->icon('outline:banknotes')->sort(10);
    }

    public static function pages(): array
    {
        return ['index' => ListCurrencies::class, 'edit' => EditCurrency::class];
    }
}
```

`php artisan make:wire-page Taxes --cluster=Settings` writes a member page with
the static already in it.

## Where The Members Are Drawn

`$subNavigationPosition` takes a `SubNavigationPosition`:

| Case | From `lg` up | Below `lg` |
| --- | --- | --- |
| `Start` — default | a column before the content | tabs above the heading |
| `End` | a column after the content | tabs above the heading |
| `Top` | tabs above the heading | tabs above the heading |

A column on a phone is a column nobody scrolls to, so below `lg` every position
is tabs. The column is laid out by a few lines of plain CSS keyed on the page's
`data-cluster-frame` attribute rather than by utilities, so it does not depend on
what the application's Tailwind build happens to contain.

## Linking Into A Cluster

Nothing about linking changes. A member keeps its key and its route name, so:

```php
CurrencyResource::url();              // /admin/settings/currencies
CurrencyResource::url('edit', $eur);  // /admin/settings/currencies/3/edit
Settings::url();                      // /admin/settings — sends you to the first member
```

A path written by hand does not follow a member into a cluster; a link built by
key or by class does.

## What A Cluster Declares

| Static | Default | Purpose |
| --- | --- | --- |
| `$slug` | the class name, kebab-cased | The key and the URL prefix of every member |
| `$navigationLabel` | the class name, humanised | The menu entry, the crumb and the navigation's accessible name |
| `$navigationIcon` | `null` | The menu entry's icon |
| `$navigationGroup` | `null` | The group the cluster's entry sits in — its members' own `group()` no longer applies |
| `$navigationSort` | `100` | Order within that group |
| `$navigationParent` | `null` | An entry the cluster's own entry sits under |
| `$permission` | `null` | The cluster route's `can:`, added to every member's route |
| `$shouldRegisterNavigation` | `true` | `false` keeps the cluster routed and out of the menu |
| `$subNavigationPosition` | `SubNavigationPosition::Start` | Where the members are drawn on each member page |

And on the member:

| Member | Declaration |
| --- | --- |
| `Pages\Page` | `protected static ?string $cluster = Settings::class;` |
| A resource | `implements BelongsToCluster` with `public static function cluster(): ?string` |
| A nested resource | nothing — it is where its parent is |

`ClusterNavigation` is the one owner the router, the menu and the page ask:
`clusterOf($class)`, `members($cluster)`, `items($cluster, $zone)`,
`landing($cluster, $zone, $user)` and `for($member, $zone)`.

## Related

- [Navigation](navigation.md) — the menu the cluster's entry sits in, and `parent()` for a branch that stays in the sidebar
- [Pages](pages.md) — `Page`, which a cluster is, and a record's own tabs
- [Routing](routing.md) — prefixes, route names and `url()`
- [Command Line](cli.md) — `make:wire-cluster` and `make:wire-page --cluster`

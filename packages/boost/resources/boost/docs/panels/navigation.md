---
order: 40
summary: How a resource reaches a menu — the entry it declares, the group it sits in, and the workspace that orders both.
---

# Navigation

A registry answers *what exists*. A menu is a different question — what is shown,
under which heading, in what order, and where each entry links. That question is
answered by three small objects: an entry a resource declares, a group the
application declares, and a workspace that arranges them.

## Declaring An Entry

A resource that should appear in a menu implements `ProvidesNavigation`:

```php
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

public static function navigation(): NavigationItem   // [tl! focus:6]
{
    return NavigationItem::make('Orders')
        ->icon('outline:shopping-cart')
        ->group('sales')
        ->sort(10)
        ->badge(fn () => Order::whereNull('shipped_at')->count(), 'danger');
}
```

Static, like identity, and for the same reason: a menu is built from every
registered resource at once, and instantiating each to ask what it is called
would compose a table and a form per entry. A resource that does not implement it
is still registered and routable — it just does not appear, which is what an
internal or nested resource wants.

`NavigationItem` is built on the canonical `HasLabel` / `HasIcon` /
`HasVisibility` concerns rather than on properties of its own, so it speaks the
same vocabulary as every other component. What it adds is only what a *menu*
needs: `group()`, `sort()` and `badge()`. A badge closure is resolved on every
read, never cached — a count of unshipped orders is wrong the moment it is.

An entry that names no label of its own is named by its resource:
`NavigationItem::make()` beside `->icon()` and `->group()` is the ordinary shape,
and the menu shows `pluralLabel()` — "Orders". A resource that wants a menu
label different from its plural passes one, and that one wins.

## Entries Under An Entry

An entry may carry entries of its own, which is the second heading a menu has —
one that is not a group, because it is a destination as well as a parent:

```php
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

NavigationItem::make('Catalogue')
    ->icon('outline:squares-2x2')
    ->children([                                                    // [tl! focus:start]
        NavigationItem::make('Products')->url(route('products.index')),
        NavigationItem::make('Categories')->url(route('categories.index'))->sort(10),
        NavigationItem::make('Archive')
            ->url(route('products.archive'))
            ->visible(fn (): bool => auth()->user()?->can('viewArchive') ?? false),
    ]);                                                             // [tl! focus:end]
```

**One level, and that is on purpose.** A child's own children are not read by
anything that draws a menu: a sidebar nesting three deep is a sidebar nobody can
hit with a mouse, and the third level belongs on the page, as tabs or a secondary
nav. Nesting further is not rejected, it is simply not drawn — a caller who does
it sees the result immediately.

`getChildren()` does the filtering and the ordering, not the view: hidden
children are dropped and the rest come back in `sort()` order, so every surface
that draws a submenu agrees about what is in it. That is the same reason
`Workspace` filters entries rather than leaving it to the sidebar. A closure is
resolved per read for the reason a badge is — children that depend on what the
current user may see must not be decided once, at registration, for everybody.

`hasChildren()` answers whether anything would be drawn, which is what decides
whether a row is a link or a disclosure. Ask it rather than counting, because the
children may still be a closure at that point. What a collapsed
[rail](../admin/sidebar.md#the-collapsed-menu) does with the difference — a
tooltip for a leaf, a popover for a parent — is the shell's business, not this
layer's.

## Groups

`group()` takes a **key**, not a heading. No resource owns the group it sits in —
several share it — so what the heading says, which icon it carries, where it sits
among the other groups and whether it is shown at all belong to a
`NavigationGroup`, declared where the application composes that part of itself:

```php
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;

public function boot(): void
{
    $this->app->make(NavigationGroups::class)->registerMany([   // [tl! focus:start]
        NavigationGroup::make('sales')
            ->label(__('nav.sales'))
            ->icon('outline:banknotes')
            ->sort(10),
        NavigationGroup::make('admin')
            ->sort(90)
            ->collapsed()
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
    ]);                                                          // [tl! focus:end]
}
```

**A group nothing declares still works.** `Workspace` makes an implicit one from
the key, so `->group('sales')` needs no registration; the heading falls back to
`Str::headline()` of the key. Registering says the five things a bare key cannot
— a heading separate from the key, an icon, an order among the other groups, one
visibility condition covering everything in the group, and whether it folds. The
typed list is the [`NavigationGroup` API](#navigationgroup-api) below.

The heading and the key are separate on purpose. `->group(__('nav.billing'))`
made the translation the array key, so the same menu was keyed differently per
locale; the key is now a slug and `HasLabel` owns the text. `hiddenLabel()` keeps
the group without drawing its heading, which is what a menu that separates with
rules rather than words wants.

Folding comes from `CanBeCollapsed` — `->collapsible()` gives the heading a
disclosure, `->collapsed()` starts it shut — the same concern `Section` and
`Repeater` use, so the pair means one thing across the framework rather than
three. A fold hides, it does not remove: a folded group still shows its entries
in the collapsed [rail](../admin/sidebar.md#the-collapsed-menu), where the
heading that would unfold it is not drawn at all.

**A provider is not the only place.** `NavigationGroups` is a container
singleton, so anything holding the container may register into it before the menu
is built — a provider's `boot()` is simply the ordinary moment. A
[module](modules.md) does not touch it at all: it returns its group from
`Module::navigation()` and `wire-core` registers it while booting the modules, so
a module ships its heading, icon and order beside the resources it groups rather
than leaving them to whoever installed it. Modules are booted in registration
order, so one listed after a module it depends on sees that module's groups
already declared.

Registering the same key twice replaces, which is how an application adjusts a
group that a package shipped without editing the package. `NavigationGroups` is
a container singleton and is otherwise a plain registry —
[its API](#navigationgroups-api) is five methods.

## The Workspace

`Workspace` arranges the result:

```php
use NyonCode\WireCore\Core\Resources\Workspace;

$nav = app(Workspace::class)->navigation();
// ['sales' => NavigationGroup, '' => NavigationGroup]   ungrouped is the '' key
```

Groups come back in `sort()` order, and groups that tie keep the order their
first entry was registered in; within a group, entries follow the same rule.
Hidden entries are dropped, and a hidden group takes its entries with it.

Entries stay keyed by **registered key**, through the grouping and through the
sort, and each one carries the URL of that key's page. Nothing declares it: the
*registry* still holds no URL — one that did would be a panel — but the menu asks
where the key is routed and fills in the answer, which is `null` for a resource
that declares no pages and for an application that routes nothing.

An entry may name its own destination with `->url('https://status.example.com')`,
and what it names always wins — an external link, or an application whose shell
has a URL scheme of its own.

`Workspace::items()` answers the same question without the headings: every
visible entry, flat, in `sort()` order, keyed by registered key — what a menu
that draws no groups shows. Entries whose group is hidden are not in it either.

Both take a [zone](routing.md#zones), and `linkedOnly: true` when the menu should hold only
what that zone can reach:

```php
app(Workspace::class)->navigation();                                    // every entry, unzoned
app(Workspace::class)->navigation(zone: 'business');                    // linked into business
app(Workspace::class)->navigation(zone: 'business', linkedOnly: true);  // [tl! focus]
```

`Workspace` does not know what a resource is, and that is deliberate. Its
entries come from the `Catalog`, which reads any number of `RegistrySource`s —
`ResourceRegistry` is one, `Widgets\DashboardRegistry` is another — so a menu
mixes resources, dashboards and anything an application registers later without
`Workspace` learning about any of them. The router and the global search palette
read the same catalogue, so registering something once reaches all three. Two sources claiming one key is refused rather than resolved: one
entry would otherwise take the other's place, and a menu that quietly lost a row
is noticed on the day that row mattered.

The label fallback above is a resource's, because `pluralLabel()` is a resource's
word. Anything else in a menu names its own entry.

Like the registry, `Workspace` owns no routing and no layout — what renders the
menu is the application's. It asks where a key is routed; it does not decide.

## Rendering The Menu

```blade
@foreach($nav as $group)
    @if($group->hasVisibleLabel())
        <p>{!! icon($group->getIcon()) !!} {{ $group->getLabel() }}</p>
    @endif

    @foreach($group->getItems() as $key => $item)
        {{-- A registered entry with no page of its own still belongs in the
             menu; it simply is not a link. --}}
        <a @if($item->getUrl()) href="{{ $item->getUrl() }}" wire:navigate @endif>   {{-- [tl! focus] --}}
            {!! icon($item->getIcon()) !!}
            {{ $item->getLabel() }}
            <x-wire::badge :color="$item->getBadgeColor() ?? 'gray'">{{ $item->getBadge() }}</x-wire::badge>
        </a>

        @if($item->hasChildren())
            <ul>
                @foreach($item->getChildren() as $child)
                    <li><a href="{{ $child->getUrl() }}" wire:navigate>{{ $child->getLabel() }}</a></li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endforeach
```

`wire-admin` ships this menu already written — see [the
sidebar](../admin/sidebar.md), which draws the same three objects, marks the
active entry from the route being rendered, and holds no state of its own. What
is above is for an application rendering its own frame.

## Changing A Menu You Did Not Register

Installing a [module](modules.md) puts its entries in the menu, and an application
adjusts them through the [`navigation.building` hook](../core/plugins/hooks.md)
rather than by not installing the module:

```php
$manager->hook(Hook::NavigationBuilding, function (NavigationBuildingPayload $payload) {
    unset($payload->items['media']);                                     // [tl! focus]
    $payload->items['docs'] = NavigationItem::make('Docs')->url('/docs')->sort(90); // [tl! focus]

    return $payload;
}, for: 'admin');
```

It runs on the **flat, keyed list**, before grouping and sorting, so it feeds
`navigation()` and `items()` alike — a hook in only one of them would let a sidebar
and a command palette disagree about what is in the menu. Preserve the keys: they
are what a consumer turns into a link. Sorting here is wasted work, because the
item's own `sort()` orders the menu afterwards.

`for:` names the **zone**, which is the only identity a menu has — it belongs to no
component and shows no single registered class. A menu built for no zone carries no
scope, so a scoped callback sits it out.

## NavigationItem API

| Method | Returns | Purpose |
| --- | --- | --- |
| `NavigationItem::make(string\|Closure\|null $label = null)` | `self` | A new entry. No label means the resource's `pluralLabel()` names it |
| `label(string\|Closure\|null $label)` | `self` | The entry's own text, overriding that fallback |
| `hiddenLabel(bool $condition = true)` | `self` | Keeps the entry, draws no text — an icon-only row |
| `icon(string\|Icon\|Closure\|null $icon, string\|IconPosition\|null $position = null)` | `self` | The icon beside the label |
| `group(string\|Closure\|null $group)` | `self` | The group **key** this entry sits under; `null` is the top level |
| `sort(int $sort)` | `self` | Order within its group; ties keep first-appearance order |
| `badge(mixed $badge, string\|Closure\|null $color = null)` | `self` | A count or short string beside the label, with an optional colour |
| `url(string\|Closure\|null $url)` | `self` | An explicit destination, which always beats the routed one |
| `children(array\|Closure $children)` | `self` | Entries under this one — one level, filtered and sorted on read |
| `visible(bool\|Closure $condition = true)` / `hidden(bool\|Closure $condition = true)` | `self` | Whether the entry is in the menu at all |
| `getLabel(): ?string` | `string\|null` | The resolved text, or `null` when nothing named it |
| `hasVisibleLabel(): bool` / `isLabelHidden(): bool` | `bool` | Whether to draw the text |
| `getIcon(): ?string` | `string\|null` | The resolved icon name |
| `getGroup(): ?string` | `string\|null` | The resolved group key |
| `getSort(): int` | `int` | The sort weight |
| `getBadge(): ?string` | `string\|null` | The badge, resolved on **every** read, never cached |
| `getBadgeColor(): ?string` | `string\|null` | Its colour, or `null` for the consumer's default |
| `getUrl(): ?string` | `string\|null` | Explicit URL, else the routed one, else `null` |
| `getChildren(): array` | `array<int, NavigationItem>` | The visible children, in `sort()` order |
| `hasChildren(): bool` | `bool` | Whether a row is a disclosure rather than a plain link |
| `isVisible(): bool` / `isHidden(): bool` | `bool` | The resolved visibility |

## NavigationGroup API

| Method | Returns | Purpose |
| --- | --- | --- |
| `NavigationGroup::make(string $key)` | `self` | The key entries point at with `group()` |
| `label(string\|Closure\|null $label)` | `self` | The heading. Separate from the key on purpose: a translated heading must not become the array key |
| `hiddenLabel(bool $condition = true)` | `self` | Group the entries, draw no heading |
| `icon(string\|Icon\|Closure\|null $icon, string\|IconPosition\|null $position = null)` | `self` | Icon beside the heading |
| `sort(int $sort)` | `self` | Order among the other groups; ties keep first-appearance order |
| `visible(bool\|Closure $condition = true)` / `hidden(bool\|Closure $condition = true)` | `self` | Shows or hides the **whole** group — one condition instead of the same one on every resource in it |
| `collapsible(bool\|Closure $condition = true)` | `self` | The heading gets a disclosure |
| `collapsed(bool\|Closure $condition = true)` | `self` | It starts folded. A fold hides; it never removes entries from the collapsed rail |
| `withItems(array $items): self` | `self` | A **copy** carrying the entries a menu shows under it — `Workspace` calls it; a declared group is a singleton, so filling it in place would make the second `navigation()` call answer differently from the first |
| `getKey(): string` | `string` | The slug, which is also `getName()` |
| `getLabel(): ?string` | `string\|null` | The heading, falling back to `Str::headline()` of the key |
| `hasVisibleLabel(): bool` | `bool` | Whether to draw the heading |
| `getItems(): array` | `array<string, NavigationItem>` | The entries under it, keyed by registered key |
| `hasItems(): bool` | `bool` | Whether the group would draw anything |
| `isCollapsible(): bool` / `isCollapsed(): bool` | `bool` | The resolved fold state |

## NavigationGroups API

The container singleton the declared groups live in. `Workspace` reads it; an
application and a package both write to it.

| Method | Returns | Purpose |
| --- | --- | --- |
| `register(NavigationGroup $group): void` | `void` | Declare one group; the same key registered again replaces, which is how an application adjusts a group a package shipped |
| `registerMany(iterable $groups): void` | `void` | The same for several at once |
| `find(string $key): ?NavigationGroup` | `NavigationGroup\|null` | The declared group with this key, or `null` when only entries name it |
| `has(string $key): bool` | `bool` | Whether a key was declared |
| `all(): array` | `array<string, NavigationGroup>` | Every declared group, keyed by key, in registration order |

## Workspace API

| Method | Returns | Purpose |
| --- | --- | --- |
| `navigation(?string $zone = null, bool $linkedOnly = false)` | `array<string, NavigationGroup>` | The menu: groups in order, each carrying its entries |
| `items(?string $zone = null, bool $linkedOnly = false)` | `array<string, NavigationItem>` | The same menu flat, without headings |
| `registered()` | `array<string, class-string>` | Every class behind the menu, entry or not |

## Catalog API

Everything an application registered, whatever kind it is — the one list the
menu, the router and the search palette read.

| Method | Returns | Purpose |
| --- | --- | --- |
| `all(): array` | `array<string, class-string>` | Every registered class, keyed, in registration order; refuses two sources claiming one key |
| `implementing(string $capability): array` | `array<string, class-string>` | Only those implementing one contract — `ProvidesNavigation`, `ProvidesPages`, `GloballySearchable` |
| `find(string $key): ?string` | `class-string\|null` | The class with this key |
| `has(string $key): bool` | `bool` | Whether a key is registered |

A registry becomes one of its sources by implementing `RegistrySource`
(`registeredClasses(): array`), which is how a dashboard registry reaches all
three surfaces without any of them importing it. Anything the router may address
also implements `HasRegistryKey` (`static key(): string`) — `ProvidesPages`
extends it, because a page that cannot be addressed cannot be given a URL.

## Related

- [Resources](resources.md) — the owner an entry names
- [Routing](routing.md) — where an entry's URL comes from, and what a zone changes
- [Modules](modules.md) — a whole area's entries declared in one manifest
- [The Sidebar](../admin/sidebar.md) — the menu `wire-admin` draws from all of this
- [Global Search](../core/global-search.md) — the palette reading the same catalogue
- [Widgets](../core/widgets/index.md) — dashboards, the other kind of thing a menu holds

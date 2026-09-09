---
order: 10
summary: The owner layer — one entity declared once, the pages that render it, the menu it appears in and the routes that reach it.
---

# Panels

`wire-panels` is the layer above the components. A table, a form and an infolist
are independent primitives that know nothing about each other; this package adds
the class that says **they are all the same entity**, and the pages, menu and
routes that follow from saying it once.

```bash
composer require nyoncode/wire-panels
```

Nothing requires this package. An application that renders its tables and forms
from its own Livewire components never installs it, and everything below keeps
working — which is the test of whether an owner layer is really optional.

## How It Works

Four things sit on top of each other, and each one is usable without the one
above it:

| Layer | What it answers | Page |
| --- | --- | --- |
| A **resource** | "this is the Order entity, this is how it lists, is edited, is shown" | [Resources](resources.md) |
| A **page** | "this Livewire component renders that surface for one record or all of them" | [Pages](pages.md) |
| The **menu** | "which of them appear where, under what heading, in what order" | [Navigation](navigation.md) |
| The **router** | "which URL reaches which page, in which zone" | [Routing](routing.md) |

A resource with no pages is still registered and still introspectable. Pages work
with no resource — write `table()` on the page and it is an ordinary `WithTable`
component. A menu entry whose key routes nowhere renders without a link rather
than failing. Each layer degrades to the one below instead of requiring it.

What holds them together is a **registry of class names**, not a panel object.
Nothing here builds a table to answer "what is in the menu": identity is static,
the surfaces are instance methods, and a menu of forty resources composes zero of
them.

## Which Package Ships What

A resource is declared across the packages that own the types it names, so an
application installs only what its resources actually use:

| You need | It lives in | Because it names |
| --- | --- | --- |
| `DescribesResource`, `DescribesRecords`, `ResourceRegistry` | `wire-core` | nothing but scalars |
| `ProvidesResourceForm` | `wire-forms` | `Form` |
| `ProvidesResourceInfolist` | `wire-core` (beside Infolists) | `Infolist` |
| `ProvidesResourceTable`, `ProvidesRelationManagers` | `wire-panels` | `Table`, `RelationManager` |
| `ListPage` and the other pages | `wire-panels` | `Table`, `Form`, the host traits |

The practical consequence: **a resource with a form and no list needs `wire-forms`
and nothing else.** Identity comes from `wire-core`, which `wire-forms` already
requires, so declaring it never pulls in a table package — its assets, migrations,
config and Livewire synthesizer included.

`wire-panels` sits above every component package and nothing depends on it. That
direction is the point: a resource composes the primitives, so the package that
owns resources is the one allowed to name all of them, and none of them may name
it back.

## Quick Start

One entity, declared once, rendered by a page of four lines:

```php
use App\Models\Order;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

final class OrderResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string   // [tl! focus:start]
    {
        return Order::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable(),
            TextColumn::make('customer.name')->label('Customer'),
        ]);
    }                                              // [tl! focus:end]
}

final class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;   // [tl! focus]
}
```

```php
// config/wire-core.php
'resources' => [
    App\Resources\OrderResource::class,
],
```

That is a working list. Adding a form makes create and edit work; adding
`navigation()` puts it in a menu; declaring `pages()` gives it URLs — each an
opt-in, in that order.

## What This Package Does Not Do

It ships no layout, no sidebar markup and no dashboard chrome. A full-page
Livewire component needs a layout and this package does not supply one — either
set `livewire.component_layout` to your own, or install
[the admin shell](../admin/overview.md), which is the ready-made answer and a
separate package for exactly this reason.

## In This Section

| Page | What it covers |
| --- | --- |
| [Resources](resources.md) | Identity, the surface contracts, naming, registration, reading the registry |
| [Pages](pages.md) | `ListPage`, `CreatePage`, `EditPage`, `ViewPage`, record resolution, embedded relation managers |
| [Navigation](navigation.md) | `NavigationItem`, `NavigationGroup`, `Workspace`, the catalogue all three surfaces read |
| [Routing](routing.md) | `pages()`, `Route::wireResources()`, the URL shape, zones, config-declared routes |
| [Modules](modules.md) | One business area's manifest — its resources, dashboards and menu heading in one class |

## Reaching A Page A Module Ships

Every resource page implements `IdentifiesHookTarget`, so a plugin hook can be
scoped to the resource it shows — `for: 'invoices'` reaches that module's list,
form and detail and nothing else. The page itself has a hook too:
[`page.mounting`](../core/plugins/hooks.md) fires once a page has mounted.

It runs **last**, which is a measurement rather than a preference: Livewire calls a
component's own `mount()` before the `mount{Trait}` hooks, so by then an edit page
has resolved its record and seeded its form — which is why a callback can add a key
to the state bag rather than have the seed overwrite it.

Change the page through its **public** surface. A page mounts once and answers every
update after from its snapshot, which carries public properties and nothing else, so
state written anywhere else is right on the first paint and gone on the second.

```php
$manager->hook(Hook::PageMounting, function (PageMountingPayload $payload) {
    $payload->page->data['team_id'] = auth()->user()->team_id;   // [tl! focus]

    return $payload;
}, for: 'invoices');
```

`$payload->title` is read-only for the same reason: a page's `$title` is protected,
so a hook that set it would be offering exactly that footgun.

## Related

- [The Admin Shell](../admin/overview.md) — the optional layout these pages render inside
- [Ready-Made Modules](../modules/index.md) — whole areas shipped as packages, built on this layer
- [Global Search](../core/global-search.md) — the command palette over the same catalogue
- [Configuration](../start/configuration.md) — where `resources` and `wire-panels.routes` are declared

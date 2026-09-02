---
order: 90
---

# Resources

A resource binds one entity to the surfaces it exposes — its list, its form, its
read-only view — so those live in one declaration instead of being wired by hand
into each Livewire component that happens to show them.

## How It Works

A table, a form and an infolist are already independent primitives, and they stay
that way: nothing about a resource changes how they work. What a resource adds is
an **owner** above them — one class that answers "this is the Order entity, this
is how it lists, this is how it is edited" — and a **registry** that can answer
"which resources exist" and "which one owns `App\Models\Order`" without building
any of those surfaces.

That split is why a resource is several small contracts rather than one large
one:

| Contract | Answers |
| --- | --- |
| `DescribesResource` | what the entity is: key, model, singular and plural label |
| `ProvidesResourceTable` | how it lists |
| `ProvidesResourceForm` | how it is created and edited |
| `ProvidesResourceInfolist` | how one record is shown read-only |

A resource implements the ones it has. A read-only audit log implements identity
and a table and nothing else, and a page that needs a form cannot be handed it by
mistake — the type says so.

`DescribesResource` is **static** and the surfaces are **instance** methods, and
the reason is mechanical rather than stylistic. A menu asks for a label, and the
registry routes a model to its owner, before anything has been instantiated; so
metadata cannot require an instance. The surfaces compose a builder that the
caller owns and has already wired to its host — exactly as `RelationManager` and
any `WithTable` component do — so those receive the instance and hand it back.

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


## Basic Usage

```php
use App\Models\Order;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Table;

final class OrderResource implements DescribesResource, ProvidesResourceTable // [tl! focus]
{
    use DescribesRecords; // [tl! focus]

    public static function modelClass(): ?string // [tl! focus:3]
    {
        return Order::class;
    }

    public function table(Table $table): Table // [tl! focus]
    {
        return $table->columns([
            TextColumn::make('number'),
            TextColumn::make('customer.name'),
        ]);
    }
}
```

`DescribesRecords` supplies the other three answers from the model class, so the
declaration above is complete: key `orders`, label `Order`, plural `Orders`.

## Naming

The key is not cosmetic. It is the config handle, the introspection name and the
route segment a page will use, so it has to survive a label change and a
namespace move — which is why it is derived from the **model**, not from the
resource class or the label:

```php
App\Models\OrderLine  →  key 'order-lines'  ·  label 'Order Line'  ·  plural 'Order Lines'
App\Models\Person     →  key 'people'       ·  label 'Person'      ·  plural 'People'
```

Pluralisation is Laravel's inflector, so irregular nouns are right without being
spelled out. Override just the answer that is wrong:

```php
public static function pluralLabel(): string
{
    return 'Line items';
}
```

A resource with no model — one backed by a `DataSource` rather than Eloquent —
returns `null` from `modelClass()` and derives its names from its own class name
instead, with a trailing `Resource` dropped. It is registered and listed like any
other; it simply cannot be found *by model*.

## Registration

Resources are declared in config:

```php
// config/wire-core.php
'resources' => [
    App\Resources\OrderResource::class,
    App\Resources\CustomerResource::class,
],
```

This is a registry, not a panel: it holds class names and answers two questions
about them. It owns no routing, no URL shell and no navigation tree.

To add one at runtime — which is what an attribute-discovery scanner would do —
resolve the registry and register directly:

```php
use NyonCode\WireCore\Core\Resources\ResourceRegistry;

app(ResourceRegistry::class)->register(OrderResource::class);
```

Registering the same class twice is a no-op, because config merging and a
provider booted twice both do it. Two *different* classes claiming one key throws
instead: the second would silently take over routing for the first.

## Reading The Registry

```php
$registry = app(ResourceRegistry::class);

$registry->all();                       // ['orders' => OrderResource::class, …]
$registry->find('orders');              // OrderResource::class | null
$registry->has('orders');               // bool
$registry->forModel(Order::class);      // OrderResource::class | null
```

Every one of these answers from the static contract alone, so building a menu
from `all()` never composes a table.

## Pages

A `ListPage` is a Livewire component that renders one resource's list. It
composes `WithTable`, so it is an ordinary table host — polling, row partials,
gestures, exports and everything else arrive unchanged, because none of them know
a resource exists.

```php
use NyonCode\WirePanels\Resources\Pages\ListPage;

final class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;
}
```

That is the whole page. Its heading defaults to the resource's plural label —
which is why that label is on the *static* contract: the page shows it without
composing anything. Set `$title` to override it.

Using a resource is not required. A page can write its own table and use no
resource at all, exactly as any `WithTable` component does:

```php
final class ListOrders extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(Order::class)->columns([
            TextColumn::make('number'),
        ]);
    }
}
```

Both paths are first class. What a page left *half* declared does is throw: a
page with neither a resource nor a `table()`, or one pointed at a resource that
declares no list, refuses loudly rather than rendering an empty table — which
would read as "no records" rather than as a mistake.

Pages own no routing. Mount one wherever the application wants it, the way any
Livewire component is mounted.

### Create, Edit And View

The other three pages follow the list. Create and edit share one form — the
resource declares it once:

```php
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

final class CreateOrder extends CreatePage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class EditOrder extends EditPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}

final class ViewOrder extends ViewPage
{
    protected static ?string $resource = OrderResource::class; // [tl! focus]
}
```

Edit and view show one record, and it arrives as a **key**:

```blade
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

Not a model, deliberately. A Livewire component's mount arguments end up in its
snapshot, so a hydrated model there is both larger than the key and stale by the
time the next request lands. The key travels; the record is resolved per request.
Override `resolveRecord()` to find it another way — a soft-deleted scope, a
tenant guard, a non-Eloquent source.

**Persistence stays the form's.** `Form` already owns validate → mutate → hooks →
persist → notify; the page only binds the model and calls `save()`. A resource
over a non-Eloquent source declares `Form::using()` in its own `form()` and these
pages are unchanged.

The pages bind the form to a `data` state path and declare the matching public
property, because binding a form to its host is the page's job — the same
division that lets a resource's `table()` know nothing about the component
rendering it. A resource needing a different path sets one in its `form()`, which
runs afterwards and wins.

A view page composes no host trait at all: read-only means no state to bind and
nothing to submit, so `Infolist` is the whole surface.

## Extended Example

An order resource with all three surfaces — the list a page renders, the form
create and edit share, and the read-only view:

```php
use App\Models\Order;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\MoneyColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Table;

final class OrderResource implements
    DescribesResource,          // [tl! focus:4]
    ProvidesResourceTable,
    ProvidesResourceForm,
    ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public function table(Table $table): Table   // [tl! focus:8]
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('customer.name')->label('Customer'),
                MoneyColumn::make('total', 'Kč'),
            ])
            ->defaultSort('number', 'desc');
    }

    public function form(Form $form): Form        // [tl! focus:7]
    {
        return $form->schema([
            TextInput::make('number')->required(),
            Select::make('customer_id')
                ->relationship('customer', 'name')
                ->required(),
        ]);
    }

    public function infolist(Infolist $infolist): Infolist  // [tl! focus:6]
    {
        return $infolist->schema([
            TextEntry::make('number'),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('total')->money('Kč'),
        ]);
    }
}
```

One `form()` serves both creating and editing on purpose — a create form and an
edit form that drift apart is the failure this shape prevents. Where they must
genuinely differ, the page passes a form the resource then shapes, rather than
the resource declaring two.

Persistence stays the form's: `Form` already owns the save lifecycle, and a
resource over a non-Eloquent source writes through `Form::using()`.

## Navigation And Workspace

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
            ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
    ]);                                                          // [tl! focus:end]
}
```

**A group nothing declares still works.** `Workspace` makes an implicit one from
the key, so `->group('sales')` needs no registration; the heading falls back to
`Str::headline()` of the key. Registering says the things a bare key cannot:

| Method | Purpose |
| --- | --- |
| `NavigationGroup::make(string $key)` | The key entries point at with `group()` |
| `label(string\|Closure\|null)` | The heading. Separate from the key on purpose: a translated heading must not become the array key |
| `icon(string\|Icon\|Closure\|null)` | Icon beside the heading |
| `sort(int)` | Order among the other groups; ties keep first-appearance order |
| `visible(bool\|Closure)` / `hidden(bool\|Closure)` | Shows or hides the **whole** group — one condition instead of the same one on every resource in it |
| `getItems(): array<string, NavigationItem>` | The entries under it, keyed by resource key |

Registering the same key twice replaces, which is how an application adjusts a
group that a package shipped without editing the package.

`Workspace` arranges the result:

```php
use NyonCode\WireCore\Core\Resources\Workspace;

$nav = app(Workspace::class)->navigation();
// ['sales' => NavigationGroup, '' => NavigationGroup]   ungrouped is the '' key
```

Groups come back in `sort()` order, and groups that tie keep the order their
first entry was registered in; within a group, entries follow the same rule.
Hidden entries are dropped, and a hidden group takes its entries with it.

Entries stay keyed by **resource key**, through the grouping and through the
sort. That key is the other half of a menu row: `NavigationItem` deliberately
holds no URL — a registry that held URLs would be a panel — so the application
maps the key to whatever it routes that resource to:

```blade
@foreach($nav as $group)
    @if($group->hasVisibleLabel())
        <p>{!! icon($group->getIcon()) !!} {{ $group->getLabel() }}</p>
    @endif

    @foreach($group->getItems() as $key => $item)
        <a href="{{ route('admin.'.$key) }}" wire:navigate>   {{-- [tl! focus] --}}
            {!! icon($item->getIcon()) !!}
            {{ $item->getLabel() }}
            <x-wire::badge :color="$item->getBadgeColor() ?? 'gray'">{{ $item->getBadge() }}</x-wire::badge>
        </a>
    @endforeach
@endforeach
```

`Workspace::items()` answers the same question without the headings: every
visible entry, flat, in `sort()` order, keyed by resource key — what a menu that
draws no groups shows. Entries whose group is hidden are not in it either.

Like the registry, `Workspace` owns no routing and no layout — what renders the
menu is the application's.

## Relation Managers

A resource can name the relation-scoped tables that belong beside its record:

```php
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;

public function relationManagers(): array   // [tl! focus:3]
{
    return [OrderItemsRelationManager::class];
}
```

`EditPage` and `ViewPage` then embed them under the form or infolist, mounted
against the record. Nothing about
[`RelationManager`](../table/relation-managers.md) changes — mounting one
directly still works exactly as before; this only removes the need to repeat that
wiring on every page. A resource declaring none renders none, which is ordinary
rather than an error.

## Introspection

`describe-resource` reports what an application's resources declare — identity,
which surfaces each has, and its navigation entry:

```text
describe-resource                  # every registered resource
describe-resource orders           # one, by key
describe-resource App\Resources\OrderResource   # or by class
```

Surfaces are reported as *declared or not*, not as their contents: composing them
would cost exactly what the static half exists to avoid, and `describe-table` and
`describe-form` already answer that for the pages that render them.

## DescribesResource API

| Method | Returns | Purpose |
| --- | --- | --- |
| `static key(): string` | `string` | Stable identifier, unique per registry |
| `static modelClass(): ?string` | `class-string\|null` | The Eloquent model owned, or `null` for a non-Eloquent source |
| `static label(): string` | `string` | Singular human name |
| `static pluralLabel(): string` | `string` | Plural human name |

## Surface Contract API

| Contract | Method | Ships in |
| --- | --- | --- |
| `ProvidesResourceTable` | `table(Table $table): Table` | `wire-panels` |
| `ProvidesResourceForm` | `form(Form $form): Form` | `wire-forms` |
| `ProvidesResourceInfolist` | `infolist(Infolist $infolist): Infolist` | `wire-core` |
| `ProvidesRelationManagers` | `relationManagers(): array` | `wire-panels` |
| `ProvidesNavigation` | `static navigation(): NavigationItem` | `wire-core` |
| `GloballySearchable` | `static globallySearchableAttributes(): array`, `static toGlobalSearchResult(object): GlobalSearchResult` | `wire-core` |

## ResourceRegistry API

| Method | Returns | Purpose |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Add a resource class; throws when it is not a resource, or when its key is taken by a different class |
| `all(): array` | `array<string, class-string>` | Every registered resource, keyed by key |
| `find(string $key): ?string` | `class-string\|null` | The resource with this key |
| `has(string $key): bool` | `bool` | Whether a key is registered |
| `forModel(string $model): ?string` | `class-string\|null` | The resource owning a model class |

## Related

- [Global Search](global-search.md) — the command palette over every registered resource
- [Relation Managers](../table/relation-managers.md) — a relation-scoped table, the owner this pattern generalises
- [Tables](../table/overview.md) — the list surface a resource declares
- [Configuration](../configuration.md) — where `resources` is declared

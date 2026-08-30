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
| `ProvidesResourceTable` | `wire-panels` | `Table` |
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

## DescribesResource API

| Method | Returns | Purpose |
| --- | --- | --- |
| `static key(): string` | `string` | Stable identifier, unique per registry |
| `static modelClass(): ?string` | `class-string\|null` | The Eloquent model owned, or `null` for a non-Eloquent source |
| `static label(): string` | `string` | Singular human name |
| `static pluralLabel(): string` | `string` | Plural human name |

## Surface Contract API

| Contract | Method |
| --- | --- |
| `ProvidesResourceTable` | `table(Table $table): Table` |
| `ProvidesResourceForm` | `form(Form $form): Form` |
| `ProvidesResourceInfolist` | `infolist(Infolist $infolist): Infolist` |

## ResourceRegistry API

| Method | Returns | Purpose |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Add a resource class; throws when it is not a resource, or when its key is taken by a different class |
| `all(): array` | `array<string, class-string>` | Every registered resource, keyed by key |
| `find(string $key): ?string` | `class-string\|null` | The resource with this key |
| `has(string $key): bool` | `bool` | Whether a key is registered |
| `forModel(string $model): ?string` | `class-string\|null` | The resource owning a model class |

## Related

- [Relation Managers](../table/relation-managers.md) — a relation-scoped table, the owner this pattern generalises
- [Tables](../table/overview.md) — the list surface a resource declares
- [Configuration](../configuration.md) — where `resources` is declared

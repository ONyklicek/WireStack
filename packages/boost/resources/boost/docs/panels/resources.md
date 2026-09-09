---
order: 20
summary: One entity bound to the surfaces it exposes — the contracts it implements, how it is named, how it is registered, and what the registry can answer without building any of them.
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
| `ProvidesBreadcrumbs` | `breadcrumbs(): array` | `wire-core` |
| `ProvidesPages` | `static pages(): array` | `wire-core` |
| `ConfiguresRoutes` | `static routeMiddleware(): array`, `static routeDomain(): ?string`, `static routePrefix(): ?string` | `wire-core` |
| `GloballySearchable` | `static globallySearchableAttributes(): array`, `static toGlobalSearchResult(object): GlobalSearchResult` | `wire-core` |

## ResourceRegistry API

| Method | Returns | Purpose |
| --- | --- | --- |
| `register(string $resource): void` | `void` | Add a resource class; throws when it is not a resource, or when its key is taken by a different class |
| `registerMany(mixed $resources): void` | `void` | Register whatever config held. A missing or malformed entry is skipped rather than fatal — a published config file with a stray value must not take the application down at boot |
| `all(): array` | `array<string, class-string>` | Every registered resource, keyed by key |
| `registeredClasses(): array` | `array<string, class-string>` | The `RegistrySource` promise the menu, the router and the palette read. The same answer as `all()`, deliberately a separate method, so `all()` can grow other callers without becoming that promise |
| `find(string $key): ?string` | `class-string\|null` | The resource with this key |
| `has(string $key): bool` | `bool` | Whether a key is registered |
| `forModel(string $model): ?string` | `class-string\|null` | The resource owning a model class |

## Related

- [Pages](pages.md) — the components that render these surfaces
- [Navigation](navigation.md) — putting a resource in the menu
- [Routing](routing.md) — giving its pages URLs
- [Modules](modules.md) — declaring a whole business area's resources at once
- [Global Search](../core/global-search.md) — the command palette over every registered resource
- [Relation Managers](../table/relation-managers.md) — a relation-scoped table, the owner this pattern generalises
- [Configuration](../start/configuration.md) — where `resources` is declared

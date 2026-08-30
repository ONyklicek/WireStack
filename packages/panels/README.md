# Wire Panels

The application owner layer for [Wire](https://github.com/nyoncode): one entity,
declared once, with the surfaces it exposes.

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;

final class OrderResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('number')]);
    }
}
```

```php
// config/wire-core.php
'resources' => [App\Resources\OrderResource::class],
```

```php
final class ListOrders extends \NyonCode\WirePanels\Resources\Pages\ListPage
{
    protected static ?string $resource = OrderResource::class;
}
```

## What ships where

A resource is declared across the packages that own the types it names, so an
application installs only what its resources actually use:

| | Package |
| --- | --- |
| `DescribesResource`, `DescribesRecords`, `ResourceRegistry`, `Workspace`, `NavigationItem`, `ProvidesNavigation` | `wire-core` |
| `ProvidesResourceForm` | `wire-forms` |
| `ProvidesResourceInfolist` | `wire-core` (beside Infolists) |
| `ProvidesResourceTable`, `ProvidesRelationManagers`, every page | **this package** |

The practical consequence: a resource with a form and no list needs `wire-forms`
and nothing else. This package sits above every component package — it requires
wire-table, wire-forms and wire-core, and nothing requires it. That direction is
the point: a resource composes the primitives, so the package that owns resources
is the one allowed to name all of them, and none of them may name it back.

## Pages

| Page | Host trait | Reads |
| --- | --- | --- |
| `ListPage` | `WithTable` | `ProvidesResourceTable` |
| `CreatePage`, `EditPage` | `WithForms` | `ProvidesResourceForm` — one `form()` serves both |
| `ViewPage` | none | `ProvidesResourceInfolist` |

`ViewPage` composes no host trait on purpose: read-only means no state to bind
and nothing to submit.

Every page also works with **no resource at all** — write `table()` or `form()`
on the page and it is an ordinary `WithTable`/`WithForms` component. A page left
*half* declared throws rather than rendering empty, because empty reads as
"nothing here" instead of as a mistake.

Edit and view take a record **key**, not a model:

```blade
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

Mount arguments land in the Livewire snapshot, where a hydrated model is both
larger than the key and stale by the next request.

## Not a panel builder

No routing, no URL shell, no navigation tree. `ResourceRegistry` holds class
names and answers two questions about them; `Workspace` groups and orders the
menu. What renders any of it is the application's.

## Documentation

Full docs: [`docs/core/resources.md`](../../docs/core/resources.md)
([česky](../../docs/cs/core/resources.md)).

## License

MIT.

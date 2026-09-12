---
name: wire-panels-development
description: Declare a resource and its pages with wire-panels — static identity, instance surfaces, the registries the menu and the router read, and the navigation entry.
---

# wire-panels Development

## When to use this skill

Use when an entity should get its own screens — a list, a create/edit form, a read-only view — or when
changing what the menu, the router or the search palette shows for one.

## Workflow

1. `describe-resource` first. It reports what is already registered, which surfaces each declares, and the
   navigation entry — a second resource on the same key is refused, not merged.
2. Write the resource: `DescribesRecords` for identity, plus one contract per surface it really has.
3. Register it in `config('wire-core.resources')` (or `ResourceRegistry::registerMany()` at runtime).
4. Add pages only for the surfaces the resource declares, and give each new concrete `WithTable`/`WithForms`
   host a `phpstan.neon` excludePaths entry.

## Patterns

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;

class OrderResource implements DescribesResource, ProvidesNavigation, ProvidesResourceTable
{
    use DescribesRecords;

    // Key, label and plural derive from the model: order-lines / Order Line / Order Lines.
    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()->icon('outline:shopping-cart')->group('sales')->sort(10);
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('number')->searchable()]);
    }
}
```

```php
// A page names the resource; the heading defaults to the plural label.
class ListOrders extends ListPage
{
    protected static ?string $resource = OrderResource::class;
}

// Edit and view take a record KEY, not a model — a hydrated model in the
// Livewire snapshot is larger than the key and stale by the next request.
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

## Rules

- **A resource is several small contracts, not one class.** `DescribesResource` (wire-core) is identity —
  `key()`, `modelClass()`, `label()`, `pluralLabel()`, all **static**. Surfaces are separate:
  `ProvidesResourceForm` (wire-forms), `ProvidesResourceInfolist` (wire-core), `ProvidesResourceTable`
  (wire-panels). Declare only what the entity has.
- **Static identity, instance surfaces.** A menu asks for a label and `ResourceRegistry::forModel()` routes
  a model to its owner *before anything is instantiated*, so metadata must answer without an instance.
- **One `form()` serves create and edit.** Persistence stays the form's; the page binds the model and calls
  `save()`. The page owns host wiring (`public ?array $data`, `statePath('data')`), the resource does not.
- **A half-declared page throws rather than rendering empty** — no resource *and* no `table()`, or a
  resource with no `ProvidesResourceTable`. Empty reads as "no records" instead of as a mistake.
- **`group()` takes a key, not a heading.** Register `NavigationGroup::make('sales')->label(…)->sort(10)`
  from a provider; a group nothing declares still works with a derived heading. Hiding a group hides its
  entries, which is the point.
- **Never inject `ResourceRegistry` into a new surface.** Read `Foundation\Registration\Catalog` and filter
  by the surface's own opt-in (`ProvidesNavigation`, `ProvidesPages`, `GloballySearchable`) — registered ≠
  listed ≠ routed ≠ searchable.
- **Which entry is active is `ActiveNavigation`'s**, matched exactly, never by prefix. An entry that means a
  whole section says `->activeWhen('settings/*')`, which *replaces* the convention rather than adding to it.
- `ViewPage` composes no host trait on purpose — read-only means no state to bind and nothing to submit.
  That is also what keeps it analysable, so do not add it to the PHPStan exclusions.

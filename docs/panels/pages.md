---
order: 30
summary: The five Livewire components that render a resource — list, create, edit, view and dashboard — what each composes, and how one record reaches them.
---

# Pages

A page is an ordinary Livewire component that renders one of a resource's
surfaces. It composes the same host trait an application would have composed by
hand, so nothing about tables, forms or infolists changes — what a page removes
is the wiring, not the primitive.

```php
use NyonCode\WirePanels\Resources\Pages\ListPage;
```

## How It Works

Five pages, and each one is a host component plus a pointer at an owner:

| Page | Composes | Reads | Renders |
| --- | --- | --- | --- |
| `ListPage` | `WithTable` | `ProvidesResourceTable` | the list |
| `CreatePage` | `WithForms` | `ProvidesResourceForm` | an empty form |
| `EditPage` | `WithForms` | `ProvidesResourceForm` | that form, bound to one record |
| `ViewPage` | nothing | `ProvidesResourceInfolist` | one record, read-only |
| `DashboardPage` | `WithWidgets` | a `Dashboard` | a grid of widgets |

`ViewPage` composes no host trait on purpose: read-only means no state to bind
and nothing to submit, so `Infolist` is the whole surface.

**What that costs is the callback action.** An infolist action — an entry button,
a section header action — dispatches to the host's `callInfolistAction()`, which
belongs to the action runtime this page does not compose; a
[halt](../core/actions/lifecycle.md#halt-execution), raised inside that same
pipeline, has nowhere to appear either. Of the five pages only `ListPage` hosts
actions, and it does so through `WithTable`. On a detail page there are two ways
round it: give the action a `url()`, which renders as a link and asks nothing of
the host — the framework's own `MediaResource` opens and downloads exactly that
way — or put the surface in a Livewire component of your own composing
[`WithActions`](../core/actions/standalone.md) and mount that on the page.

Every page also works with **no owner at all** — write `table()`, `form()` or
`widgets()` on the page itself and it is an ordinary host component. What a page
left *half* declared does is throw: a page with neither a resource nor a
`table()`, or one pointed at a resource that declares no list, refuses loudly
rather than rendering an empty table — which would read as "no records" rather
than as a mistake.

Pages own no routing. Mount one wherever the application wants it, the way any
Livewire component is mounted; [Routing](routing.md) is the opt-in that gives
them URLs.

## The List Page

`ListPage` renders one resource's list. It composes `WithTable`, so it is an
ordinary table host — polling, row partials, gestures, exports and everything
else arrive unchanged, because none of them know a resource exists.

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

Both paths are first class.

## Create, Edit And View

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

One `form()` serves both creating and editing on purpose — a create form and an
edit form that drift apart is the failure this shape prevents. Where they must
genuinely differ, the page passes a form the resource then shapes, rather than
the resource declaring two.

## How A Record Reaches The Page

Edit and view show one record, and it arrives as a **key**:

```blade
@livewire(EditOrder::class, ['record' => $order->getKey()])
```

Not a model, deliberately. A Livewire component's mount arguments end up in its
snapshot, so a hydrated model there is both larger than the key and stale by the
time the next request lands. The key travels; the record is resolved per request.
Override `resolveRecord()` to find it another way — a soft-deleted scope, a
tenant guard, a non-Eloquent source. It returns `Model|RecordContract|null`, so
an override really can hand back something that is not a model:

```php
protected function resolveRecord(): RecordContract
{
    return new ArrayRecord($this->reports->find($this->record), 'id');
}
```

A **view page** renders that as it stands: the infolist asks the contract for
each entry's value rather than reaching into it. An **edit page** refuses it,
with a message naming the way out — a form's save lifecycle is Eloquent (its
relationship repeaters, its optimistic lock, `$model->save()`), so a record that
does not unwrap to a model cannot be bound to one. Override `form()` on the page,
leave the model unbound, and give the form its own command.

**Persistence stays the form's.** `Form` already owns validate → mutate → hooks →
persist → notify; the page only binds the model and calls `save()`. A resource
over a non-Eloquent source declares `Form::using()` in its own `form()` and these
pages are unchanged.

The pages bind the form to a `data` state path and declare the matching public
property, because binding a form to its host is the page's job — the same
division that lets a resource's `table()` know nothing about the component
rendering it. A resource needing a different path sets one in its `form()`, which
runs afterwards and wins.

## Where A Save Lands

A successful save is two decisions, and the pages only agree on the first one.
**Create redirects; edit stays.** The record a create page just filed exists, the
form that filed it is still full, and the next press of the same button files a
second one — so the page it was made on is the one place the user must not be
left standing. An edit is already on its record's own page, and after a save that
page holds exactly what was written.

Where a create lands is the record's own page, and the list is the fallback:

```text
view  →  edit  →  index  →  stay
```

Each step is skipped when the resource declares no such page, when the
application routes none, when the record cannot be put in a URL — a resource over
a non-Eloquent source has no key to build one from — or when the page declares a
permission this user lacks. The last one matters because `ResourceRoutes` turns
that same declaration into `can:` middleware: redirecting into a 403 is strictly
worse than the page they were already looking at. Nothing routed at all is the
final `stay`, which is what a page mounted by hand or rendered inside something
else gets.

Either page names its own destination by overriding one method:

```php
final class CreateOrder extends CreatePage
{
    protected static ?string $resource = OrderResource::class;

    protected function getRedirectUrl(mixed $record): ?string   // [tl! focus:3]
    {
        return $this->pageUrl('index');
    }
}
```

It receives whatever the form's save returned — a model for the ordinary Eloquent
case, and whatever `Form::using()` answered for anything else — and `null` means
stay. `pageUrl()` is the sibling-URL helper described below;
[`reachablePageUrl()`](#reaching-its-other-pages) is the same thing minus the
pages this user may not open.

The redirect uses `wire:navigate`, like every other link in a panel.

**The success toast comes with it.** A notification is a browser event, and the
navigate replaces the document that would have shown it — so the driver flashes
it too, and the toast container renders that on arrival. Nothing has to be
switched on: it is what the [session driver](../core/notifications/index.md#drivers)
has always done, now that something reads it.

## Dashboard Pages

A dashboard is declared the same way a resource is, and `DashboardPage` is its
list page: a Livewire component composing `WithWidgets`, pointed at an owner.

```php
use NyonCode\WirePanels\Resources\Pages\DashboardPage;

final class SalesDashboardPage extends DashboardPage
{
    protected static ?string $dashboard = SalesDashboard::class;   // [tl! focus]
}
```

Everything `WithWidgets` already does — stamping widget keys, filtering by
visibility, the grid, and answering a poll tick with one widget instead of the
whole page — arrives unchanged. As with the resource pages, writing the widgets
on the page and declaring no dashboard is equally first class. See
[Widgets](../core/widgets/index.md) for what a dashboard declares and what a widget is.

## Embedded Relation Managers

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

## Where A Page Sits

A menu knows which of its entries is active. Nothing knows that an edit page is
*inside* the list it came from until the page says so, and that is the one piece
of navigation a menu cannot supply. All four resource pages implement
[`ProvidesBreadcrumbs`](resources.md#surface-contract-api) and answer it already:

```php
$page->breadcrumbs();
// [ NavigationItem('Orders')->url('/admin/orders'), NavigationItem('Order #17') ]
```

The crumbs are `NavigationItem`s rather than a shape of their own — a crumb is a
label and, usually, a URL, which is exactly what [that
class](navigation.md#navigationitem-api) has carried since the menu needed it. The
last crumb carries no URL: it is the page you are on. **Two crumbs at most**,
because that is the whole depth these pages have — a list is inside nothing, and
a trail of one draws nothing at all, so a list page pays for none of this.

The zone the trail links into is read **once, at mount**, and kept in the public
`$breadcrumbZone`. It has to be public to survive the round trip, and it has to be
read at mount because that is the only moment it can be read: during a Livewire
update `Route::currentRouteName()` is `livewire.update`, so a crumb that
re-derived the zone would link correctly on the first paint and out of the zone on
every one after ([ADR 0027](routing.md#zones)).

A page that is not a resource page — a settings screen, a module's own list —
implements the contract itself and returns whatever trail it has. A page inside
nothing says so by not implementing it, rather than by returning an empty array
from a method it was forced to have.

## Reaching Its Other Pages

A page links to its siblings with `pageUrl()`, and asks what they require with
`pagePermission()`:

```php
$this->pageUrl('edit', $order);   // /admin/orders/17/edit, or null
$this->pagePermission('edit');    // the ability the route also guards, or null
```

Both live on the **page** rather than on the resource, and the zone is the reason:
the same resource mounted in two zones has two different edit URLs, and a resource
has no way to know which one it is being drawn in. The page does — it read the
zone at mount and kept it.

`pagePermission()` is derived from the resource's `pages()` declaration rather
than restated, which is the whole point of having it: `ResourceRoutes` turns that
same declaration into `can:` middleware, so a button hidden by one and a route
guarded by the other cannot drift apart. A page declared as a bare class string
requires nothing, and neither does the button.

`null` is a real answer in both — a resource that declares no such page, or an
application that routes none, gets a button without a link rather than a broken
one.

`reachablePageUrl()` is the two of them asked together: the URL, or `null` where
this user could not open it anyway.

```php
$this->reachablePageUrl('view', $order);   // the URL, or null — unrouted or not allowed
```

It is what [a create page redirects on](#where-a-save-lands), and the reason it
exists rather than each caller pairing the two: the declared permission is also
`can:` middleware on that route, so a link that ignored it would be a link to a
403.

`hookKey()` is the third of these. It answers with the registered key of whatever
the page shows, which is what turns
[`for: 'invoices'`](../core/plugins/hooks.md) into something an application can
actually write: the table and the form on this page are built inside code the
application may not own — a module shipped as a package — so that key is the only
handle it has on them. A page declaring nothing answers `null` and is scoped by
class instead, because throwing there would turn a hook's *absence* of scope into
a render failure.

## Page API

Every resource page composes `BelongsToResource`, which is the half that is about
*which* resource the page shows:

| Member | Type | Purpose |
| --- | --- | --- |
| `protected static ?string $resource` | `class-string\|null` | The owner, or none — a page writing its own surface is equally first class |
| `protected ?string $title` | `string\|null` | Heading override. Each page decides its own fallback, because a list wants the plural and a form the singular |
| `public ?string $breadcrumbZone` | `string\|null` | The zone read at mount and carried across the round trip |
| `getTitle(): ?string` | `string\|null` | The heading; the trail's last crumb is it |
| `breadcrumbs(): array` | `array<int, NavigationItem>` | Where the page sits — two crumbs at most |
| `static resourceClass(): ?string` | `class-string\|null` | The declared resource, for anything asking from outside |
| `hookKey(): ?string` | `string\|null` | The registered key a plugin hook addresses this page's surfaces by |
| `pageUrl(string $page, mixed $record = null): ?string` | `string\|null` | *(protected)* Where one of this resource's pages is, in this page's zone |
| `pagePermission(string $page): ?string` | `string\|null` | *(protected)* The ability that page requires, as the resource declared it |
| `reachablePageUrl(string $page, mixed $record = null): ?string` | `string\|null` | *(protected)* The same URL, minus the pages this user may not open |
| `requireResource(string $surface): object` | `object` | *(protected)* The declared resource, checked; throws rather than rendering an empty page |
| `resourceLabel(): ?string` | `string\|null` | *(protected)* The resource's singular label |

What each page adds is only its own surface:

| Page | Adds |
| --- | --- |
| `ListPage` | `table(Table $table): Table` |
| `CreatePage` | `public ?array $data`, `form(Form $form): Form`, `save(): mixed`, `getRedirectUrl(mixed $record): ?string` |
| `EditPage` | the same, plus `recordData(): array` and a `mountedRecord()` that seeds the form |
| `ViewPage` | `infolist(): Infolist` |
| `DashboardPage` | `protected static ?string $dashboard`, `protected static ?string $layoutKey`, `static dashboardClass(): ?string`, `getWidgets(): array`, `getWidgetColumns(): int`, `widgetLayoutKey(): ?string` |

`DashboardPage` composes no resource at all — it has its own `$title`,
`getTitle()` and `hookKey()`, the last of which answers with the **dashboard's**
key. `$layoutKey` is `null` on purpose: a page declaring its widgets inline has no
registered key, and deriving one from the class name would tie a user's saved
layout to a class they do not control, orphaning every layout on a rename with no
error to say so. So it is named there, or the page is not customisable.

Edit and view resolve one record, through `ResolvesOneRecord`:

| Member | Type | Purpose |
| --- | --- | --- |
| `public mixed $record` | `mixed` | The record's **key**. Public because Livewire carries it across requests |
| `mount(mixed $record = null): void` | `void` | Takes the key and calls `mountedRecord()` |
| `mountedRecord(): void` | `void` | *(protected)* Hook for what a page does once its record is known — the edit page seeds its form here, the view page needs nothing |
| `resolveRecord(): Model\|RecordContract\|null` | `Model\|RecordContract\|null` | *(protected)* The record itself. Override for a soft-deleted scope, a tenant guard, or a non-Eloquent source |
| `nativeRecord(): mixed` | `mixed` | *(protected)* The native object behind it — what a relation manager is mounted with, because relations are queried on the model |
| `requireEloquentRecord(): ?Model` | `Model\|null` | *(protected)* The same as a model, or a refusal naming the way out |
| `recordAttributes(): array` | `array<string, mixed>` | *(protected)* Its attributes, whichever kind of record it is |

Edit and view also compose `EmbedsRelationManagers`, whose one method is
`relationManagers(): array`.

## A Layout Is Yours

A full-page Livewire component needs a layout, and this package does not supply
one — set `livewire.component_layout` to your own, or install
[the admin shell](../admin/overview.md), which brings one.

## Related

- [Resources](resources.md) — the owner these pages read
- [Navigation](navigation.md) — `NavigationItem`, the shape a breadcrumb is made of
- [Routing](routing.md) — giving pages URLs, in one zone or several
- [Tables](../table/overview.md) and [Forms](../forms/overview.md) — the surfaces the pages host
- [Infolists](../core/infolists/index.md) — what a view page renders
- [Widgets](../core/widgets/index.md) — what a dashboard page renders

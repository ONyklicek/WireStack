---
order: 30
summary: The Livewire components that render a resource — list, create, edit, view and dashboard — plus a page of your own; what each composes, the actions beside their heading, and how one record reaches them.
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
| `ManagePage` | `WithTable` | `ProvidesResourceTable`, `ProvidesResourceForm` | the list, with create and edit as modals |
| `CreatePage` | `WithForms`, `WithActions` | `ProvidesResourceForm` | an empty form |
| `EditPage` | `WithForms`, `WithActions` | `ProvidesResourceForm` | that form, bound to one record |
| `ViewPage` | `WithActions` | `ProvidesResourceInfolist` | one record, read-only |
| `DashboardPage` | `WithWidgets` | a `Dashboard` | a grid of widgets |

`ViewPage` composes no form or table trait: read-only means no state to bind and
nothing to submit, so `Infolist` is the whole surface.

**Every page can run actions**, and each runs them through one engine. The list
has the one `WithTable` brings; the other three compose
[`WithActions`](../core/actions/standalone.md), which is what runs their
[header actions](#header-actions), a [halt](../core/actions/lifecycle.md#halt-execution)
raised inside one, and on the view page an infolist's own callback actions — an
entry button, a section header action — which dispatch to the host's
`callInfolistAction()` and used to find nobody there.

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

## Unsaved Changes

The create and edit pages ask before their input is left behind — a reload, a
closed tab, a `wire:navigate` link in the menu. What is compared is the form's
state against what was last saved, so typing a value and putting it back asks
nothing, and pressing *Save* is never stopped by the warning it makes
unnecessary. The mechanism is the forms bundle's `wireUnsavedChanges`
([Forms](../forms/overview.md#warning-before-unsaved-input-is-lost)); the page
only says it wants it, on its `data` path and its `save()` method.

A page that should not ask turns it off:

```php
protected function warnsAboutUnsavedChanges(): bool
{
    return false;
}
```

## List Tabs

A list can be several lists of the same records — all invoices, the open ones,
the overdue ones. A tab is a name and how it narrows the query:

```php
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WirePanels\Resources\ListTab;

final class ListInvoices extends ListPage
{
    protected static ?string $resource = InvoiceResource::class;

    protected function tabs(): array                  // [tl! focus:start]
    {
        return [
            ListTab::make('all')->showCount(),
            ListTab::make('open')->query(fn (Builder $q) => $q->whereNull('paid_at'))->showCount(),
            ListTab::make('overdue')
                ->icon('outline:exclamation-triangle')
                ->badgeColor('danger')
                ->query(fn (Builder $q) => $q->where('due_at', '<', now()))
                ->showCount(),
        ];
    }                                                  // [tl! focus:end]
}
```

**A tab narrows the base query**, before search, filters and sort, so everything
the table does still works inside it. It wraps whatever `modifyQueryUsing()` the
table already had rather than replacing it: a resource that never lists archived
invoices keeps not listing them on every tab. It is applied to the table
`WithTable` has just composed, so it holds on a page that writes its own
`table()` too.

**The active tab is `$activeTab`, carried in the URL as `?tab=`.** A link, a
reload and the back button land on the same tab. An empty or unknown name is the
first tab, and switching starts the list again from its first page.

**A count is asked of the same base scope** — the resource's scope, not the
search or the filters — with one `count()` per tab that shows one, on every
render. `badge()` sets a number of your own instead and asks nothing. The count
is gray unless `badgeColor()` says otherwise.

```php
ListTab::make(string $name)                     // the name the URL carries
->label(string|Closure|null $label)             // default: the name, humanised
->icon(string|Icon|Closure|null $icon)
->query(?Closure $callback)                     // fn (Builder $query) => $query->…
->showCount(bool $condition = true)             // count the tab's records
->badge(int|Closure|null $count)                // a number of your own
->badgeColor(string|Color|null $color)          // default 'gray'
```

## Page Widgets

Any page — a list, a record, a page of your own — can put widgets above and
below its content:

```php
protected function headerWidgets(): array
{
    return [StatsOverviewWidget::make()->stats([Stat::make('Open', (string) Invoice::open()->count())])];
}
```

`footerWidgets()` is the same below the content, `pageWidgetColumns()` says how
wide the row is (3 by default), and a `null` entry is skipped. They are
**drawn, not hosted**: the page renders them through the grid a dashboard uses
and does not become a widget host. A widget that polls, loads lazily or has
actions of its own needs `WithWidgets` behind it, which is what a
[dashboard page](#dashboard-pages) is for.

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

## Header Actions

A page puts actions beside its heading by declaring them:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

final class ViewOrder extends ViewPage
{
    protected static ?string $resource = OrderResource::class;

    protected function headerActions(): array     // [tl! focus:start]
    {
        return [
            Action::make('ship')
                ->label('Mark shipped')
                ->requiresConfirmation()
                ->visible(fn (Order $record): bool => $record->shipped_at === null)
                ->action(fn (Order $record) => $record->update(['shipped_at' => now()])),
            $this->deleteHeaderAction(),
        ];
    }                                              // [tl! focus:end]
}
```

They are ordinary [actions](../core/actions/index.md) — a modal, a form, a
wizard, a confirmation and a halt all work — drawn by the same button view as
every other action, beside the title from `sm` up and under it on a phone.

**A record page's actions are about its record.** Edit and view mount every
action against the record they show, so `visible(fn ($record))` and a
record-aware `authorizeUsing()` are asked about the same record when the button
is drawn and when it is clicked. Without that, the button would ask about the
order and the click about nothing.

**Where the click lands depends on the page, and nothing else does.** The list
page's actions run through its table's engine — a second engine would be a
second modal stack on one component — so a click calls `openHeaderActionModal()`
or `executeHeaderAction()`, and the table finds the page's action before its own.
The other pages call `mountAction()`. `headerActionClick()` is where each page
says which; the button is `Action::render()` either way.

An `ActionGroup` works as a header action too, and an entry that is `null` is
skipped, so a conditional action is written inline:

```php
protected function headerActions(): array
{
    return [
        ...parent::headerActions(),
        $this->headerActionRecord()?->locked_at !== null ? null : Action::make('lock')->action(...),
    ];
}
```

### The ones that come with it

**The list page offers *New*** — a link to the create page, drawn when the
resource routes one and this user may open it. It asks exactly what the create
route's `can:` middleware asks, so it can never offer something the router
would refuse, and that is why it is on by default. Keep it with
`...parent::headerActions()`, or build it yourself with `createHeaderAction()`.

**Edit and view offer *Delete*, and draw it only when asked** —
`$this->deleteHeaderAction()`, as above. Who may delete what is an
application's rule: the users module will not delete the last super-admin, and a
default button would walk straight past that. When asked, it decides like this:

```text
the model has a policy     →  the policy's delete() decides
it has none                →  whoever may open the record's edit page may delete
there is no edit page      →  nobody — a read-only resource grows no Delete
```

It confirms, deletes through the model — so a model with soft deletes
soft-deletes — flashes a success notification and goes back to the list with
`wire:navigate`. Where no list is routed, it stays.

## A Resource On One Page

An entity whose form is a field or two — tags, units, payment terms — does not
need a page to create one and another to edit it. `ManagePage` is the list with
both as modals over it, and *Delete* on the row:

```php
use NyonCode\WirePanels\Resources\Pages\ManagePage;

final class ManageTags extends ManagePage
{
    protected static ?string $resource = TagResource::class;   // [tl! focus]
}

public static function pages(): array
{
    return ['index' => ManageTags::class];
}
```

The resource's one `form()` renders in both modals. **What they save is the
model's**: a modal keeps its state in the action's frame rather than in a page's
`$data`, so creating is `Model::create($data)` and editing is
`$record->update($data)` with the validated data. A form that needs the page
lifecycle — a relationship repeater, an optimistic lock, `Form::using()` — wants
a create and an edit page instead.

**Who may do what is the model's policy** — `create`, `update`, `delete` — when
it has one. Without one there is no check here at all and the page's route is
the guard: whoever may open the page may manage its records. It is deliberately
not a check that answers yes, because every authorization callback refuses a
request with no signed-in user, and an unguarded page would list records with
no way to change them.

## Trashed Records

A resource over a soft-deleting model can manage its trash in the panel rather
than hide it, by saying so:

```php
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;

final class InvoiceResource implements DescribesResource, ManagesTrashedRecords, ProvidesResourceTable
{
    // …the model uses SoftDeletes
}
```

That one declaration reaches every page, so the list and a record's page cannot
disagree about whether a trashed record exists:

- **The list** gains a `trashed` filter, *Restore* and *Force delete* on a
  trashed row, and both as bulk actions — which act only on the trashed records
  of a selection, because a force delete swept across live rows is data loss,
  not the mirror image of a restore.
- **A record page** opens a trashed record rather than answering 404, hides
  `deleteHeaderAction()` on it, and offers `restoreHeaderAction()` and
  `forceDeleteHeaderAction()` to a page that asks for them:

```php
protected function headerActions(): array
{
    return [$this->deleteHeaderAction(), $this->restoreHeaderAction(), $this->forceDeleteHeaderAction()];
}
```

Each of them is decided like *Delete*: the model policy's `restore()` or
`forceDelete()` when there is a policy, the record's edit page otherwise, per
record. The contract over a model that does not use `SoftDeletes` refuses with a
message naming both, instead of failing inside a query.

## A Page Of Your Own

A board, a calendar, a report: a page that is not one of a resource's surfaces
still wants the same heading, trail and header actions as the pages around it.
`Page` is those, around a view you write:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WirePanels\Pages\Page;

final class TaskBoard extends Page
{
    protected static string $view = 'livewire.task-board';   // [tl! focus]

    protected ?string $title = 'Board';

    protected function headerActions(): array      // [tl! focus:start]
    {
        return [
            Action::make('newTask')
                ->form([TextInput::make('title')->required()])
                ->action(fn (array $data) => Task::create($data)),
        ];
    }                                               // [tl! focus:end]

    /** A card's own button — the same engine, declared beside the header. */
    protected function actions(): array
    {
        return [
            Action::make('moveTask')->action(fn (array $arguments) => $this->move($arguments)),
        ];
    }

    protected function getViewData(): array
    {
        return ['lanes' => Task::query()->get()->groupBy('status')];
    }
}
```

The view is the content only — the page draws the heading above it and the
modal host below it, and the component's public properties, `$this` and
whatever `getViewData()` returns all reach it:

```blade
<div class="grid grid-cols-3 gap-4">
    @foreach($lanes as $status => $tasks)
        <section>…</section>
    @endforeach
</div>
```

A trail is an opt-in: implement `ProvidesBreadcrumbs` and return one. A page
that names no view refuses to render rather than drawing an empty frame.

### Registering It

A page is registered the way a resource is, and then it is everywhere a
resource is — routed by `Route::wireResources()`, listed in the menu, shown by
`wire:resources` — with nothing else written for it:

```php
// config/wire-panels.php
'pages' => [App\Livewire\Pages\TaskBoard::class],

// or a whole folder — config/wire-core.php
'discover' => ['pages' => ['App\\Livewire\\Pages' => app_path('Livewire/Pages')]],
```

Where it goes and what the menu says are statics on the page, each with a
default:

```php
final class TaskBoard extends Page
{
    protected static ?string $slug = 'board';                    // [tl! focus:start]
    protected static ?string $navigationLabel = 'Board';
    protected static ?string $navigationIcon = 'outline:view-columns';
    protected static ?string $navigationGroup = 'work';
    protected static int $navigationSort = 30;
    protected static ?string $permission = 'tasks.view';        // [tl! focus:end]
    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'livewire.task-board';
}
```

The key — the URL segment and the menu's key — is `$slug`, or the class name
kebab-cased with a trailing `Page` dropped (`TaskBoardPage` → `task-board`); the
label is `$navigationLabel`, or the class name humanised. `$permission` becomes
the route's `can:` middleware **and** hides the menu entry from someone who lacks
it, so the menu never offers a page its route would refuse;
`$shouldRegisterNavigation = false` keeps a page routed and out of the menu. Two
pages on one key are refused, as two resources are.

The registry fills itself on its first read rather than at boot, so a page is
there for config-declared routes too. A page can still be routed from an
owner's `pages()` instead — a page about one record always is, under
`{record}/…` — or mounted by hand.

`Page` is a convenience, not a requirement. It composes `HostsPageActions`, and a
component of your own composing the same trait gets the same header actions
without extending anything.

A board — records in lanes, dragged between them — is exactly this: a `Page`
that composes [`WithBoard`](../sortable/board.md) and names
`wire-sortable::board.content` as its view.

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
last crumb carries no URL: it is the page you are on. **Two crumbs at most** for
an ordinary resource, because that is the whole depth its pages have — a list is
inside nothing, and a trail of one draws nothing at all, so a list page pays for
none of this. A [nested resource](resources.md#nested-resources) starts its
trail at its parent's list and record.

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

## The Record's Other Pages

An edit screen and the read-only one beside it are two pages of the same record,
and until a page says so the only way between them is back through the list. The
menu cannot help — it knows resources, not records — and breadcrumbs lead *up*
rather than across. So a record's pages draw themselves as a row of tabs above
the page's own content.

**Nothing is declared for it.** `pages()` is already the list, and the router
already knows which of those take a record — it is the same question it answers
when it builds `{record}` into the URL. A page is a tab when its URI carries one:

```php
class OrderResource implements DescribesResource, ProvidesPages
{
    public static function pages(): array
    {
        return [
            'index'   => ListOrders::class,          // no record → not a tab
            'create'  => CreateOrder::class,         // no record → not a tab
            'view'    => ViewOrder::class,           // [tl! focus:start]
            'edit'    => EditOrder::class,
            'history' => RoutePage::make(OrderHistory::class)
                ->uri('{record}/history')            // this is what makes it a tab
                ->icon('outline:clock')
                ->permission('orders.audit')
                ->sort(30),                          // [tl! focus:end]
        ];
    }
}
```

A page that named itself nothing is named by the framework: `view` and `edit`
carry a translation in every shipped locale, and anything else is its own key,
humanised — `history` reads as *History*. `RoutePage::label()` overrides both.

```php
$page->subNavigation();
// ['view' => NavigationItem('View'), 'edit' => NavigationItem('Edit'), 'history' => NavigationItem('History')]
```

The tabs are [`NavigationItem`s](navigation.md#navigationitem-api), like the
crumbs above them, because "a label, an icon and a URL" has one owner here.

**Three rules decide what is drawn**, and all three are about not drawing
something misleading:

- A page this reader may not open is **left out** — the same ability the route is
  guarded by, asked before the link is drawn. A tab that lands on a 403 is worse
  than no tab.
- A page whose URL cannot be built is **left out**. Unlike a menu row, which
  honestly says "registered, not routed here", a tab that goes nowhere is just
  broken. This is also what empties the bar for a record that is not Eloquent:
  there is no key to put in the URL.
- **Fewer than two tabs is none.** A single tab is the page's own heading written
  a second time — the rule the breadcrumb trail already follows for a trail of
  one.

The current tab is marked from the **page kind** — `Zone::currentPage()`, read off
the route name — rather than by comparing the tab's URL with the current one,
where a trailing slash or a query string decides whether a tab lights up. Like
the zone beside it, it is read once at mount and kept in the public
`$currentPage`, because during a Livewire update the route name is
`livewire.update` ([ADR 0027](routing.md#zones)) and an edit page re-renders on
every keystroke.

The view and edit pages compose this already. A page of your own joins the row by
composing the same trait:

```php
class OrderHistory extends Component
{
    use BelongsToResource;
    use LinksToRecordPages;
    use ResolvesOneRecord;

    protected static ?string $resource = OrderResource::class;
}
```

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
| `breadcrumbs(): array` | `array<int, NavigationItem>` | Where the page sits — two crumbs at most, four for a nested resource |
| `public ?string $currentPage` | `string\|null` | The kind of page this is — `view`, `edit`, or one the resource named — read at mount and carried |
| `subNavigation(mixed $record = null): array` | `array<string, NavigationItem>` | The record's other pages, keyed by page kind. Empty below two |
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
| `ListPage` | `table(Table $table): Table`, `tabs(): array`, `public string $activeTab`, `getListTabs(): array`, `getActiveListTab(): ?ListTab` |
| `CreatePage` | `public ?array $data`, `form(Form $form): Form`, `save(): mixed`, `getRedirectUrl(mixed $record): ?string` |
| `EditPage` | the same, plus `recordData(): array` and a `mountedRecord()` that seeds the form |
| `CreatePage`, `EditPage` | `warnsAboutUnsavedChanges(): bool` — `true` by default |
| every page but the dashboard | `headerWidgets(): array`, `footerWidgets(): array`, `pageWidgetColumns(): int` |
| `ViewPage` | `infolist(): Infolist` |
| `ManagePage` | everything `ListPage` has, plus `createHeaderAction()` as a modal, *Edit* and *Delete* on the row, `guardedByPolicy(Action, string, bool): Action`, `manageForm(): Form` |
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

Every page but the dashboard composes `InteractsWithHeaderActions`, and every page
but the list composes it through `HostsPageActions`, which adds `WithActions`:

| Member | Type | Purpose |
| --- | --- | --- |
| `headerActions(): array` | `array<int, Action\|ActionGroup\|null>` | *(protected)* Declare the page's header actions; `null` entries are skipped |
| `getHeaderActions(): array` | `array<int, Action\|ActionGroup>` | The declared actions, resolved once per request |
| `headerActionRecord(): ?Model` | `Model\|null` | *(protected)* The record the actions are about — edit and view answer with theirs |
| `headerActionClick(): ResolvesActionClick` | `ResolvesActionClick` | *(protected)* Which Livewire method a click lands in on this page |
| `createHeaderAction(): ?Action` | `Action\|null` | *(protected, list)* The ready-made *New*, or `null` where there is no create page to open |
| `deleteHeaderAction(): DeleteAction` | `DeleteAction` | *(protected, edit and view)* The ready-made *Delete* — confirm, delete, back to the list |
| `mayDeleteRecord(): bool` | `bool` | *(protected, edit and view)* Policy first, then the edit page's permission, then no |
| `restoreHeaderAction(): RestoreAction` | `RestoreAction` | *(protected, edit and view)* *Restore*, drawn only on a trashed record |
| `forceDeleteHeaderAction(): ForceDeleteAction` | `ForceDeleteAction` | *(protected, edit and view)* *Force delete*, drawn only on a trashed record, back to the list |

`Page` adds only what a page of your own needs:

| Member | Type | Purpose |
| --- | --- | --- |
| `protected static string $view` | `string` | The view drawn under the heading. Required |
| `protected ?string $title` | `string\|null` | The heading, or none |
| `getViewData(): array` | `array<string, mixed>` | *(protected)* Anything the view needs beside the public properties |

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

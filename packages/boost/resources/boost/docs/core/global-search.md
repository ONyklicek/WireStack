---
order: 80
summary: One command palette over every registered resource — opt a resource in, mount the component, and a term reaches orders, customers and invoices in one list.
---

# Global Search

One search box over everything the application has registered. The user opens a
palette, types, and gets records from every [resource](../panels/resources.md) that opted
in — grouped by resource, capped per resource, and filtered by what that user is
allowed to see.

The palette gains a resource the moment one is registered. It keeps no list of
its own, so there is nothing to forget to update.

## How It Works

Every keystroke is a Livewire round trip, debounced by 250 ms. What runs, in
order:

1. `GlobalSearchPalette` — the component you mount — holds the term, the open
   flag and the keyboard cursor, and no query at all. It asks `GlobalSearch`.
2. `GlobalSearch` reads the [`ResourceRegistry`](../panels/resources.md) and skips every
   resource that does not implement `GloballySearchable`. Identity and
   searchability are separate opt-ins: a resource that should not be searchable —
   an audit log, a join table given a resource for routing — says so by not
   implementing the contract, rather than by returning an empty array from a
   method it was forced to have.
3. For each remaining resource it runs **one** query:
   `WHERE (a LIKE %term% OR b LIKE %term%) LIMIT 5`, over the attributes the
   resource declared.
4. Every matched record goes through an authorization check, and what survives is
   handed back to the resource, which turns it into a `GlobalSearchResult`.
5. A resource that matched nothing is left out of the result entirely rather than
   mapped to an empty list, so rendering group headings is just iterating.

**What it costs** is one query per opted-in resource per keystroke. That is the
reason for both the per-resource cap and for the rule that attributes are plain
columns — a join per resource per keystroke is how a palette stops being a fast
first guess.

**Defaults and edges:**

- An **empty term returns nothing**. Matching everything would mean "here is your
  whole database" the instant the modal opens.
- `%` and `_` are wildcards in `LIKE`, so the term is escaped, with the escape
  character declared explicitly (`ESCAPE '!'`). MySQL treats `\` as the default
  escape and SQLite does not, so a query relying on that default would mean two
  different things on two supported databases.
- A resource with **no model** (one over a non-Eloquent `DataSource`) or with an
  **empty attribute list** contributes nothing rather than erroring.
- Groups are headed by the resource's `pluralLabel()`, not by its key — the key
  is what routes and configures a resource, not what a user reads.

## Opting A Resource In

Implement `GloballySearchable` — two static methods, static for the same reason
identity and navigation are: the palette asks every registered resource what it
can search before anything is instantiated.

```php
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;

final class OrderResource implements DescribesResource, GloballySearchable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Order::class;
    }

    public static function globallySearchableAttributes(): array   // [tl! focus:start]
    {
        // Columns on this resource's own model. Not the status: a palette that
        // answered "paid" with every paid order is a report, not a jump-to.
        return ['number', 'customer'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: self::key(),
            recordKey: $record->getKey(),
            title: $record->number,
            subtitle: $record->customer.' · '.$record->status,
            icon: 'outline:document-text',
        );
    }                                                              // [tl! focus:end]
}
```

`GlobalSearchResult` is deliberately flat and already resolved — the palette
renders many rows at once and must never call back into a resource per row:

| Argument | Type | What it is |
| --- | --- | --- |
| `resourceKey` | `string` | Which resource produced it; the palette groups on this |
| `recordKey` | `int\|string` | The record's key, for `wire:key` and for the click |
| `title` | `string` | The line a user reads first |
| `subtitle` | `?string` | Context under it — a status, an email, a date |
| `url` | `?string` | Where selecting it goes; derived when omitted, `null` when nothing routes the record |
| `icon` | `?string` | Icon name, resolved like every other icon in the framework |

**Note what the example does not pass.** A row already carries the two halves of
its own URL — the resource key and the record key — so the framework builds it:
`urlFor($resourceKey, 'view', ['record' => $recordKey])`, in the
[zone](../panels/routing.md#zones) the palette was opened in. Writing a path here is
copying what the router already knows, and the copy is the one that goes stale:
before this was derived, this repository's own workbench carried two literal
paths and both were wrong — one pointed at a shell, the other at a page with no
record in it, and nothing failed.

Pass `url:` explicitly only when the row goes somewhere the convention does not
reach — an external system, a report, a page outside the resource's own. An
explicit URL always wins.

A row with a `null` url still renders and is still arrowed through; Enter on it
does nothing rather than navigating somewhere invented. That is the answer for a
resource that declares no pages, and for one routed in a different zone than the
palette was opened in.

## Beyond Records: Navigation And Commands

The palette lists four kinds of row, and each is a separate opt-in.

**Records** come from `GloballySearchable`, above. **Navigation entries** need
nothing at all — anything already in your menu is already in the palette, filtered
by the same `isVisible()` the sidebar uses and pointed at the same zone. **Commands**
and **record actions** come from one contract:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Contracts\ProvidesCommands;

final class InvoiceResource implements DescribesResource, ProvidesCommands
{
    public static function commands(?object $record = null): array
    {
        if ($record !== null) {
            return [
                Action::make('archive')                                    // [tl! focus:3]
                    ->label('Archive invoice')
                    ->requiresConfirmation()
                    ->action(fn (Invoice $record) => $record->archive()),
            ];
        }

        return [
            Action::make('recount')                                        // [tl! focus:2]
                ->label('Recount invoices')
                ->action(fn () => Recount::dispatch()),
        ];
    }
}
```

One method, not two. `$record` is null when the palette is asking what stands on
its own, and carries a record when the user has drilled into one — press <kbd>→</kbd>
on a result to see what can be done with it, and <kbd>←</kbd> to come back.

An action is listed only if the current user may run it. That is `canExecute()` —
visibility **and** `permission()` / `authorize()` / `authorizeUsing()` — and it is
checked before the row exists, not when it is clicked, because a palette that lists
the label of a forbidden action has already leaked it.

### Keyboard

Focus stays in the search box the whole time; the arrow keys move a cursor, not
the focus, and the box tells a screen reader where that cursor is through
`aria-activedescendant`.

| Key | What it does |
|---|---|
| <kbd>↓</kbd> / <kbd>↑</kbd> | move the cursor, wrapping at either end |
| <kbd>→</kbd> | show the active record's actions |
| <kbd>←</kbd> | back to the results, with the term intact |
| <kbd>Enter</kbd> | follow, run or hand on the cursor's row |
| <kbd>Tab</kbd> | move real focus onto a row; it stays inside the dialog |
| <kbd>Esc</kbd> | close |

<kbd>Tab</kbd> and the cursor are allowed to disagree, and what you activate is
always what happens — a row names itself when it is clicked or tabbed to, and only
<kbd>Enter</kbd> in the box defers to the cursor.

### What Enter Does

The palette owns no modal, so an action that has to ask something is never run by
it. Which of three things happens is decided by the action itself:

| The action | What the palette does |
|---|---|
| has nothing to ask | runs it where it stands, and closes |
| is about a record, and has a modal | goes to the record's page with `?action=` |
| stands alone, and has a modal | dispatches `wire-palette-action` for a host on screen |

The middle row needs no wiring: a page showing one record reads `?action=` on
arrival and mounts it — **if it owns an action host**. None of the shipped pages
does: `ListPage` composes `WithTable`, the form pages compose `WithForms`, and
`ViewPage` deliberately composes no host trait at all. Add
[`WithActions`](actions/standalone.md) to your own page and both the query
parameter and the dispatch are answered.

A standalone command is not navigated anywhere, because an index page owns no
action host either — sending you there for a modal that will not open is worse
than not moving you.

The order of the groups matters and is fixed: **records, then navigation, then
commands**. A term that matches both a record and a command belongs to the record —
typing `INV` should open the invoice, not run "Recount **inv**oices".

## Mounting The Palette

Mount the component once, in the layout:

```blade
@livewire('wire-global-search')
```

**Opening it is the application's business.** The component listens for an
`open-global-search` event and binds no key itself, because a framework that
claimed ⌘K on every page would be taking a combination the application may
already use. This is what an app writes:

```blade
<div
    x-data
    @keydown.window.cmd.k.prevent="$dispatch('open-global-search')"
    @keydown.window.ctrl.k.prevent="$dispatch('open-global-search')"
>
    <button type="button" x-on:click="$dispatch('open-global-search')">
        Search… <kbd>⌘K</kbd>
    </button>
</div>

@livewire('wire-global-search')
```

Inside the palette the keys are bound already: **↑/↓** walk the rows, **Enter**
opens the active one, **Escape** closes. The cursor is a flat index over every
group rather than a (group, row) pair, because that is what the arrow keys move
through — Down on the last row of one group reaches the first row of the next.
Changing the term puts the cursor back to the top, or Enter would open something
from a result set the user was no longer looking at.

The dialog is teleported to `<body>`, like every modal in the framework, so it is
never clipped by a positioned ancestor.

**In a zoned application it needs no configuration.** The palette reads its
[zone](../panels/routing.md#zones) from the page it was rendered on and keeps it in a
public property, so results point back into the zone the user is in — the same
layout mounted under `/admin` and `/business` links into each. Set it explicitly
only when the palette sits in a shell that is not itself a resource route:

```blade
@livewire('wire-global-search', ['zone' => 'business'])
```

It has to be a property rather than a lookup: the search runs on a Livewire
request, where the current route is Livewire's own endpoint and the zone can no
longer be asked for. See [Zones](../panels/routing.md#zones).

## Authorization And Tenancy

A palette that lists the title of a forbidden record **has leaked it**, whether
or not the click is refused afterwards. So:

- **Tenancy needs nothing here.** It is a global Eloquent scope, so a query built
  from `Model::query()` is already narrowed to the current tenant.
- **Per record**, the searcher asks the model's policy for `view`. When a model
  has **no policy registered at all**, the check falls open — Laravel's own
  answer for an unguarded model, and what keeps the palette usable in an
  application that authorizes nowhere. A resource that must never be listed
  without a check registers a policy, the same thing it would do for any other
  read.

The cap is applied **before** authorization filters, so a user without access to
any of a resource's five best matches sees that resource drop out of the results
rather than seeing a hole.

## When Plain Columns Are Not Enough

`globallySearchableAttributes()` stays the plain-columns answer. An application
whose match needs a join, a full-text index or an external search service
replaces the *search*, not the contract: `GlobalSearch::searchResource()` and
`matchAny()` are `protected` for exactly this, and the palette resolves its
searcher from the container.

```php
use NyonCode\WireCore\GlobalSearch\GlobalSearch;

class ScoutGlobalSearch extends GlobalSearch
{
    protected function searchResource(string $resource, string $term, int $perResource): array   // [tl! focus]
    {
        // …ask the search service, map hits through $resource::toGlobalSearchResult()
    }
}

// A service provider:
$this->app->bind(GlobalSearch::class, ScoutGlobalSearch::class);   // [tl! focus]
```

## Searching Without The Palette

`GlobalSearch` is an ordinary service. A custom surface — a page of results, a
mobile screen, an API endpoint — can ask it directly and render the answer
itself:

```php
use NyonCode\WireCore\GlobalSearch\GlobalSearch;

$groups = app(GlobalSearch::class)->search('INV-100');
// ['orders' => [GlobalSearchResult, …], 'customers' => [GlobalSearchResult, …]]

$more = app(GlobalSearch::class)->search('INV-100', perResource: 20);

// Results linked into one zone rather than the unzoned mount point.
$zoned = app(GlobalSearch::class)->search('INV-100', zone: 'business');
```

## API

```php
GlobalSearch::search(string $term, int $perResource = 5, ?string $zone = null): array
GlobalSearch::PER_RESOURCE_LIMIT                                  // 5

GlobalSearchResult::withUrl(?string $url): GlobalSearchResult     // the same row, pointed somewhere
```

`search()` reads the [`Catalog`](../panels/navigation.md#catalog-api), so the palette gains a
resource the moment one is registered and never keeps a list of its own. A
registered thing that opts into searching but has no model — a dashboard — is
skipped rather than asked.

The contracts a resource implements — each one an opt-in on its own:

```php
static globallySearchableAttributes(): array          // plain column names on the model
static toGlobalSearchResult(object $record): GlobalSearchResult

static commands(?object $record = null): array        // ProvidesCommands; ActionContract[]
```

The two questions the palette asks about an action before it offers or runs one,
answered through the container so no surface has to import the Actions module:

```php
ClassifiesComponentActions::needsPrompt(ActionContract $action): bool
ClassifiesComponentActions::isRunnable(ActionContract $action, mixed $context = null): bool
RunsComponentActions::runComponentAction(ActionContract $action, array $context = []): void
```

The palette component, for a custom trigger or a test:

```php
$palette->open(): void                 // also bound to the `open-global-search` event
$palette->close(): void                // clears the term, so it opens empty next time
$palette->moveDown(): void
$palette->moveUp(): void
$palette->select(): mixed              // follows, runs or hands on the active row
$palette->selectedUrl(): ?string       // where the active row goes
$palette->drillDown(): void            // show the active record's actions // [tl! focus:2]
$palette->drillUp(): void              // back to the results, term intact
$palette->isDrilledDown(): bool
$palette->flatResults(): array         // every row, in render order
$palette->groupLabels(): array         // [group key => heading]
$palette->zone                         // ?string — the zone it was opened in

GlobalSearchPalette::ACTION_EVENT      // 'wire-palette-action'
GlobalSearchPalette::ACTION_PARAMETER  // 'action'
```

## Narrowing A Resource's Search

A searchable resource shipped by an installed [module](../panels/modules.md)
declares its own attributes, and an application constrains what the palette lists
through the [`search.querying` hook](plugins/hooks.md):

```php
$manager->hook(Hook::SearchQuerying, function (SearchQueryingPayload $payload) {
    $payload->query->whereNull('archived_at');   // [tl! focus]

    return $payload;
}, for: 'invoices');
```

It fires **once per resource**, not once per term — which is what makes it
addressable: `for:` names the searched resource's catalogue key or its model, so a
callback written for one module does not run against the whole palette.

It runs before the query executes and **before** the per-record `canView()` check,
so a callback can narrow the rows and cannot widen its way past a policy.

## Related

- [Resources](../panels/resources.md) — the registry the palette reads, and the identity contract every resource implements
- [Authorization](../start/authorization.md) — policies, tenancy, and what is not scoped
- [Modals](modals.md) — the teleport-to-body pattern the dialog follows

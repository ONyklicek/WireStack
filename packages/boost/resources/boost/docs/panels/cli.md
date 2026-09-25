---
order: 80
summary: The artisan commands that write a resource, its pages, a page of your own and a relationship's table — and the one that reads back what is registered.
---

# Command Line

Everything a panel is made of can be written by hand, and every command here
writes exactly what the pages expect — so the first file is right and the rest
is yours. None of them edits a file you already have unless you pass `--force`.

## Generating A Resource

```bash
php artisan make:wire-resource Order
```

writes the resource and the three pages an ordinary resource has:

```text
app/Resources/OrderResource.php
app/Livewire/Resources/Orders/ListOrders.php
app/Livewire/Resources/Orders/CreateOrder.php
app/Livewire/Resources/Orders/EditOrder.php
```

The resource declares its `pages()`, so once it is registered and
`Route::wireResources()` runs in your panel's route group, its URLs exist. The
edit page is written with `$this->deleteHeaderAction()` in its header — the one
default the pages leave to you, because who may delete what is your rule
([Pages](pages.md#header-actions)).

| Option | What it changes |
| --- | --- |
| `--model=App\Domain\Order` | The model, when it is not `App\Models\{Name}` |
| `--generate` | Fields, columns and infolist entries read off the model's table |
| `--view` | A read-only `ViewPage` and an `infolist()` on the resource |
| `--simple` | One [`ManagePage`](pages.md#a-resource-on-one-page) instead of three pages |
| `--soft-deletes` | [`ManagesTrashedRecords`](pages.md#trashed-records), and *Restore* / *Force delete* in the record pages' headers |
| `--register` | Adds the class to a published `config/wire-core.php` |
| `--force` | Overwrites files that already exist |

**`--generate` reads the table, not the model.** Each column becomes a field, a
column and an entry by its type — a boolean a `Toggle` and a `BooleanColumn`, a
date a `DateTimePicker::make()->asDate()` and a `->date()` column, a text column
a `Textarea`, a number `->numeric()`, anything else a `TextInput` — and a column
that is neither nullable nor defaulted is `required()`. The key, the timestamps,
`deleted_at` and credentials are left out. It is a starting point written once:
a table that does not exist yet writes the resource without generated lines
and says so, rather than failing.

**`--register` only writes where it can do so safely** — into a published
`config/wire-core.php` that has a `resources` list, and only once. Anywhere else
it prints the line to add.

## Generating A Page Of Your Own

```bash
php artisan make:wire-page TaskBoard
php artisan make:wire-page History --resource=Order
```

The first writes a [`Page`](pages.md#a-page-of-your-own) and the view it draws —
`app/Livewire/Pages/TaskBoard.php` and
`resources/views/livewire/pages/task-board.blade.php`. The second writes a page
about **one record** of `OrderResource`: it composes `BelongsToResource`,
`ResolvesOneRecord` and `LinksToRecordPages`, so it takes the record's key and
draws the record's tabs. The command prints what is left to do: a page of its own
is [registered like a resource](pages.md#registering-it) — `config('wire-panels.pages')`
or a discovered folder — and is then routed and listed by itself; a record page
is routed from its resource's `pages()`:

```php
'history' => RoutePage::make(\App\Livewire\Resources\Orders\History::class)->uri('{record}/history'),
```

— which is also what makes it one of the record's tabs. Routing is the owner's
declaration, so the command prints it rather than editing the resource.

## Generating A Relationship's Table

```bash
php artisan make:wire-relation-manager Order items
```

writes `app/Livewire/Resources/Orders/ItemsRelationManager.php`, a
[`RelationManager`](../table/relation-managers.md) over the `items` relationship
with a table to fill in, and prints how to embed it: have the resource implement
`ProvidesRelationManagers` and return the class from `relationManagers()`.

## Reading Back What Is Registered

```bash
php artisan wire:resources
php artisan wire:resources orders
```

The first lists every registered resource — its key, class, model, the surfaces
it declares, its pages and how many routes they have. The second shows one
resource page by page: the component, every route that reaches it (a page
routed in two zones is two rows), its URI and the permission it requires, or
*not routed*. Both read the catalogue and the router — the same answers the
menu, the palette and `Route::wireResources()` work from — so what it prints is
what the application actually has.

## Changing What They Write

```bash
php artisan vendor:publish --tag=wire-panels::stubs
```

copies the templates to `stubs/wire-panels/`, and every command here reads a
published template before its own: `resource.stub`, `resource-page.stub`,
`page.stub`, `page-view.stub`, `relation-manager.stub` and
`dashboard-page.stub`.

## Related

- [Resources](resources.md) — what a generated resource declares
- [Pages](pages.md) — what the generated pages are
- [Routing](routing.md) — giving the pages URLs

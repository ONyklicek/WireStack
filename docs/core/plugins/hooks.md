---
order: 30
summary: "The hook system: where the framework asks whether anyone wants a say, how to narrow a hook to one component, and the typed hooks that make a contract of it."
---

# Hooks

A hook is a named point where the framework stops and asks whether anyone wants a
say. This page is the list of those points, what each one is handed, what it may
return — and how to scope a hook so it fires for one component rather than for
every table in the application.

## Hook System

Hooks let plugins and application code communicate through named callbacks.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('orders.exporting', function (array $payload): array {
        $payload['query']->where('tenant_id', auth()->user()->tenant_id);

        return $payload;
    });
}
```

Run the hook from your own service or component:

```php
use NyonCode\WireCore\Core\Plugin\PluginManager;

$payload = app(PluginManager::class)->runHook('orders.exporting', [
    'query' => Order::query(),
]);

$query = $payload['query'];
```

A hook only affects runtime behavior when some code calls `runHook()` or `runTypedHook()` for that hook name. Registering a hook stores the callback; it does not automatically patch table, form, or action behavior.

### Dispatching A Typed Hook Of Your Own

Offering a hook means three things before you can offer one: check a `PluginManager` is bound at all, check anything is listening, and only then pay for a payload. `HookDispatch` owns that, and it is what every hook in this framework dispatches through:

```php
use NyonCode\WireCore\Core\Plugin\HookDispatch;

$payload = HookDispatch::typed('orders.exporting', fn () => new ExportingOrders( // [tl! focus]
    query: $this->query(),                                                       // [tl! focus]
    format: $format,                                                             // [tl! focus]
));                                                                              // [tl! focus]

$query = $payload !== null ? $payload->query : $this->query();                   // [tl! focus]
```

The payload arrives as a **closure**, which is the point: building one may mean reading a table's columns or a dashboard's widgets, and an application that installs no plugin should pay for none of it. The closure runs only after a callback has been found to receive it.

**`null` means nobody listened, not "nothing changed".** Folding the two together with `?? $original` restores your own value whenever a callback empties an array — and emptying it is a legitimate answer, so a filter that removed every column would silently look like a no-op. Compare against `null` explicitly.

### Shipped Hooks

`Hook` is the canonical spelling of every name below, and a plain string is always accepted in its place — `Hook::TableComposing` and `'table.composing'` are the same name.

| Hook | Runs | Changes |
|---|---|---|
| `Hook::TableComposing` | once, when a host has composed its table | the table itself — columns and filters as rendered, searched and sorted |
| `Hook::TableConfiguring` | inside the query service, per query | what the planner is about to read |
| `Hook::TableQuerying` | after the plan is built, before it runs | the query, and a forced sort |
| `Hook::TableQueried` | after every pipe has applied | nothing — observation |
| `Hook::FormConfiguring` | once, when a schema becomes a config | the schema |
| `Hook::FormSaving` | before validated data is persisted | the data |
| `Hook::FormSaved` | after the record exists | nothing — observation |
| `Hook::ActionExecuting` | before the action pipeline | the context |
| `Hook::ActionExecuted` | after it completes | nothing — observation |
| `Hook::InfolistConfiguring` | once, when an infolist's schema is read | the schema |
| `Hook::WidgetConfiguring` | once, before a host filters its widgets | the widgets, before their keys are stamped |
| `Hook::ExportConfiguring` | once per export, whichever way it is delivered | the query and the columns the file gets |
| `Hook::NavigationBuilding` | every time the menu is built | the entries, keyed by what registered them |
| `Hook::PageMounting` | once, when a resource page has mounted | the page's own public state |
| `Hook::SearchQuerying` | once per resource, per global search | that resource's query |
| `Hook::ImportConfiguring` | once per import, whichever way it is delivered | the mapping and the import config |
| `Hook::CellUpdating` | before an inline cell edit is written | the value, or refuses the write |
| `Hook::FormFilling` | when a form is filled from a record | what the fields arrive holding |

**`table.composing` and `table.configuring` are not two names for one moment.** Configuring runs inside `TableQueryService`, on the arrays the planner is about to consume, so a column added there is searched and sorted on and **never rendered**. Composing runs on the table instance the host built, so a column added there is a column the user sees. To add a column, reach for `TableComposing`; to steer a query, `TableConfiguring` or `TableQuerying`.

Everything except the seven hooks in the first block is **typed-only**: it takes a payload object through `runTypedHook()` and has no array counterpart. Those seven are dispatched both ways for backwards compatibility, and each callback belongs to exactly one dispatcher — see [Which Dispatcher Gets Your Callback](#which-dispatcher-gets-your-callback). New hooks do not get an array form: the two-dispatch arrangement is a 2.x compatibility debt, not a pattern to extend.

### Scoping A Hook To One Component

An unscoped callback runs for every table, form or action in the application. `for:` narrows it to one — by the registered key of the resource a page shows, by the host component's class, or by the model:

```php
$manager->hook(Hook::TableComposing, $addColumn, for: 'invoices');            // one resource
$manager->hook(Hook::FormConfiguring, $addField, for: Invoice::class);        // one model
$manager->hook(Hook::TableComposing, $addColumn, for: ListInvoices::class);   // one page
```

This is what makes an installed [module](../../panels/modules.md) adjustable: its list is built inside code you do not own, so the key it registered under is the handle you have on it. A page shows its key because it implements `IdentifiesHookTarget` — every resource page does, and a dashboard page answers with the key of the dashboard it shows; a standalone component shows none and is scoped by class or model instead.

Two hooks name something other than a component, because they belong to no component:

| Hook | What `for:` names |
|---|---|
| `Hook::NavigationBuilding` | the [zone](../../panels/navigation.md) the menu is being built for |
| `Hook::SearchQuerying` | the searched resource's catalogue key, or its model |

A scoped callback is skipped where a dispatch carries no target at all, including hooks your own code dispatches without one. Running a callback written for one module against a component it has never seen is the worse of the two mistakes. So a menu built for no zone, and an infolist over a plain array rather than a model, both sit a scoped callback out.

### Hook Return Values

Array hooks receive the current payload array.

| Callback return | Result |
|-----------------|--------|
| `array` | Replaces the payload for the next callback |
| `null` or another non-array value | Keeps the current payload unchanged |
| exception | Bubbles up to the caller |

### Hook Priority

Callbacks run by ascending priority. Lower numbers run earlier.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: -100);
    $manager->hook('table.querying', fn (array $payload) => $payload);
    $manager->hook('table.querying', fn (array $payload) => $payload, priority: 100);
}
```

Suggested ranges:

| Priority | Use for |
|----------|---------|
| `-100` | Security, tenancy, scoping |
| `0` | Normal feature behavior |
| `100` | Audit, logging, telemetry |

Callbacks with the same priority keep their registration order.

### Runtime Hooks

These hooks are emitted by the current packages:

| Hook | Package | When | Payload | Consumes returned payload |
|------|---------|------|---------|---------------------------|
| `table.composing` | Table | On the table instance the host built — a column added here is one the user sees | `table`, `columns`, `filters` | Yes, reads the modified arrays |
| `table.configuring` | Table | Inside `TableQueryService`, on the arrays the planner is about to consume — searched and sorted on, never rendered | `table`, `columns`, `filters` | Yes, reads the modified arrays |
| `table.querying` | Table | Before the table query is planned | `table`, `columns`, `filters`, `sort_column`, `sort_direction`, `search` | Yes, reads `force_sort_column` and `force_sort_direction` |
| `table.queried` | Table | After the query is built, with the plan behind it | `table`, `query`, `plan` | No |
| `form.configuring` | Forms | Once, when a form's schema becomes its config — the counterpart of `table.configuring`, and how a plugin adds a field to someone else's form | `form`, `schema` | Yes, reads the modified schema |
| `form.saving` | Forms | After mutation and before persistence | `config`, `data` | Yes, reads modified `data` |
| `form.saved` | Forms | After persistence and relationship save | `config`, `record` | No |
| `action.executing` | Table | Before the action pipeline runs | `action`, `actionName`, `actionType`, `recordIds`, `data`, `component` | No |
| `action.executed` | Table | After the action pipeline runs | `action`, `actionName`, `actionType`, `recordIds`, `result`, `component` | No |
| `infolist.configuring` | Core | Once, when an infolist's schema is read for rendering — the read-only half of `form.configuring` | `infolist`, `schema` | Yes, reads the modified schema |
| `widget.configuring` | Core | Before a host filters its widgets by visibility | `host`, `widgets` | Yes, reads the modified list |
| `navigation.building` | Core | While `Workspace` assembles the menu, before it is grouped | `items`, `zone` | Yes, reads the modified entries |
| `search.querying` | Core | Per resource in the palette, **before** `get()` and before `canView()` — a callback narrows the query and cannot widen past the policy check | `query`, `term`, `resource` | Yes, reads the modified query |
| `export.configuring` | Table | After the visibility filter, so a callback sees what the file would contain rather than everything the table declares | `export`, `query`, `columns` | Yes, reads the modified query and columns |
| `page.mounting` | Panels | When a resource page mounts, before it renders | `page`, `title`, `zone` | Yes, reads the modified title |
| `infolist.configuring` | Core | Once, when an infolist's schema is read — the read-only counterpart of `form.configuring` | `infolist`, `schema` | Yes, reads the modified schema |
| `widget.configuring` | Core | On the declared widgets, before their keys are stamped and before visibility filters them | `host`, `widgets` | Yes, reads the modified list |
| `export.configuring` | Table | In `buildTableExport()`, so a streamed download and a queued file are the same export | `export`, `query`, `columns` | Yes, reads both |
| `navigation.building` | Core | On the flat, keyed entry list, before grouping and sorting | `items`, `zone` | Yes, reads the modified items |
| `page.mounting` | Panels | Once a resource page has mounted — after its own `mount()`, so the record is resolved | `page`, `title`, `zone` | No — the page is what a callback changes |
| `search.querying` | Core | Per resource, on the query the palette is about to run | `query`, `term`, `resource` | Yes, reads the query |
| `import.configuring` | Table | In `importTable()`, which a queued import re-enters — the other half of `export.configuring` | `import`, `columns`, `path` | Yes, reads the columns |
| `cell.updating` | Table | In `CellEditPipeline::commit()`, after the column's own checks and before the write | `column`, `columnName`, `record`, `value`, `oldValue`, `refusal` | Yes, reads both |
| `form.filling` | Forms | In `Form::fill()` — the way *in*, where `form.saving` is the way out | `form`, `data` | Yes, reads the data |

Several of those have an ordering worth knowing, because it is what makes them useful rather than merely early:

- **`widget.configuring` runs before keys are stamped.** A widget's key comes from its index in the unfiltered list, so a widget added afterwards would carry none — and be unreachable by a poll tick — or take one another widget already answers to. It also runs before the visibility filter, so an added widget's own `visible()` is honoured.
- **`export.configuring` runs in `buildTableExport()`**, which both `exportTable()` and `queueTableExport()` call. A hook on the download alone would leave the queued copy uncovered, and nobody would notice until they compared two files. It fires *after* column visibility, so what you receive is what the file would contain.
- **`cell.updating` runs last inside the commit.** The column's permission check, the optimistic-lock check and its validation have all passed by then, so a callback narrows what is written and cannot widen past a guard the column declared. It sits in the pipeline rather than in its two callers because the inline editor and the fill handle both funnel through it — a hook on one of them would be a rule that a drag across a column quietly escapes. Setting `$payload->refusal` stops the write and reaches the browser as the cell's own error message.
- **`import.configuring` needed no `buildTableImport()`.** Unlike its export counterpart, a queued import already re-enters through `importTable()` — `RunImportJob` mounts the host and calls it — so one dispatch covers both deliveries. It fires *after* the `ImportAction`'s authorization check, so the path a callback can read is one the action has already agreed to open, and the path is read-only.
- **`form.filling` is not dispatched from `getInitialState()`.** That answers the different question of what a control needs before anything is bound, and an edit page calls both — a hook on each would fire twice per page, which is how a callback that appends ends up appending twice.
- **`page.mounting` runs last.** Livewire calls a component's own `mount()` before the `mount{Trait}` hooks, so by then an edit page has resolved its record and seeded its form — which is why a callback can add a key to the state bag rather than have the seed overwrite it. Change the page through its **public** surface: a page mounts once and answers every update after from its snapshot, which carries public properties and nothing else, so state written anywhere else is right on the first paint and gone on the second. That is also why `$title` on the payload is read-only — a page's `$title` is protected, so a hook that set it would be offering exactly that.

The plugin manager does not enforce hook names. For application hooks, use names that describe your boundary, such as `orders.exporting`, `orders.exported`, `billing.invoice.saving`, or `crm.customer.synced`.

### Example: Add A Row To A Module's Detail Page

The shape every module package needs: a resource ships a list, a form and a detail page, and the application adds to one of them without owning the class.

```php
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;

public function register(PluginManager $manager): void
{
    $manager->hook(
        Hook::InfolistConfiguring,                                                   // [tl! focus]
        function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload { // [tl! focus]
            $payload->schema = [...$payload->schema, TextEntry::make('crm_id')];     // [tl! focus]
                                                                                     // [tl! focus]
            return $payload;                                                         // [tl! focus]
        },                                                                           // [tl! focus]
        for: 'users',                                                                // [tl! focus]
    );
}
```

The same three lines, with `Hook::FormConfiguring` and a field, add it to the form beside it. That symmetry is the point of the pair.


### Example: Force Table Sort In A Hook

The sortable package uses `table.querying` to force a sort while a table is in reorder mode. The same pattern works for application-specific query rules.

```php
public function register(PluginManager $manager): void
{
    $manager->hook('table.querying', function (array $payload): array {
        $table = $payload['table'] ?? null;

        if (! $table instanceof OrdersTable) {
            return $payload;
        }

        $payload['force_sort_column'] = 'position';
        $payload['force_sort_direction'] = 'asc';

        return $payload;
    }, priority: -100);
}
```

Use `modifyQueryUsing()` when you only need to change one table. Use `table.querying` when the rule belongs to a reusable integration.

## Macros: The Other Half

A hook is for a component you never see. A **macro** is for one you do hold, when all you want is new vocabulary on a class you did not write:

```php
Column::macro('money', fn (): Column => $this->alignment('right')->formatStateUsing(fn ($v) => number_format($v, 2)));

TextColumn::make('total')->money();
```

`Table`, `Form`, `Column`, `Field`, `Filter` and `BaseAction` are macroable. Declare macros in a plugin's `boot()`, never in `register()` — see [Plugins](index.md#lifecycle).

Reach for a macro when you build the component and only want to say it in fewer words; reach for a hook when the component is built inside a module you installed.

## Typed Hooks

`runTypedHook()` is available for extension points that prefer object payloads instead of arrays.

```php
final class ExportingOrders
{
    public function __construct(
        public Builder $query,
        public string $format,
    ) {}
}

$payload = app(PluginManager::class)->runTypedHook(
    'orders.exporting',
    new ExportingOrders(Order::query(), 'csv')
);
```

Callbacks receive the payload object. Returning an object replaces the payload for the next callback; returning `null` or another non-object keeps the current payload.

```php
$manager->hook('orders.exporting', function (ExportingOrders $payload): ExportingOrders {
    $payload->query->where('tenant_id', auth()->user()->tenant_id);

    return $payload;
});
```

**One asymmetry worth knowing before you pick a variant:** the typed `table.querying` payload is dispatched *after* the plan is built, so it is for reading a finished plan and its result is not read back. A sort override belongs on the array hook, which runs before the planner.

Core also ships typed payload DTOs under `NyonCode\WireCore\Core\Plugin\Hooks` for common table, form, and action hook shapes — and the runtime **already dispatches them**. Every built-in lifecycle point runs both dispatchers back to back: `table.configuring`, `table.querying` and `table.queried` from `TableQueryService`, `form.saving` and `form.saved` from the save handler, `action.executing` and `action.executed` from the action runtime. So a callback on any of those hooks can take `TableQueryingPayload`, `FormSavingPayload`, `ActionExecutingPayload` and the rest directly.

### Which Dispatcher Gets Your Callback

Because both dispatchers run at every lifecycle point, each callback must belong to exactly one of them, and **the first parameter's type hint is what decides**:

| First parameter | Dispatcher | Payload |
| --- | --- | --- |
| `array $payload` | `runHook()` | the array |
| a DTO, or any other type hint | `runTypedHook()` | the object |
| **no type hint, or no parameter** | `runHook()` | the array |

Type-hint it. An unhinted callback is treated as the array form for backwards compatibility, which means it silently never sees the typed payload:

```php
// Runs on the array dispatch only — $payload is an array.
$manager->hook('form.saving', function ($payload) { /* … */ });   // [tl! --]
// Says which payload it wants, and gets it.
$manager->hook('form.saving', function (FormSavingPayload $payload): FormSavingPayload { // [tl! ++]
    $payload->data['audited_at'] = now();                                                // [tl! ++]
                                                                                          // [tl! ++]
    return $payload;                                                                      // [tl! ++]
});                                                                                       // [tl! ++]
```

## Related

- [Plugins](index.md) — where hooks are registered from
- [Extending Surfaces](extending.md) — the registries a hook often writes into
- [Examples And Testing](examples.md) — hooks in two finished plugins
- [Save Lifecycle](../../forms/save-lifecycle.md) — the form's own hook points

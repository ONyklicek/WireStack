---
order: 95
summary: Versioning, the two live lines, what 2.0 changed, and the steps that make an upgrade uneventful.
---

# Upgrade Guide

How to move between Wire versions safely and where to find breaking changes.

---

## Versioning

The Wire ecosystem is one monorepo released as [fourteen
packages](project-map.md#packages), split from a single tag. Their versions move
in lockstep, so install or constrain them as a set — a `wire-table` from one
minor beside a `wire-core` from another is not a combination that is tested.

Wire follows semantic versioning from **1.0** onward: a breaking change waits for
a major, and a minor only adds. Two lines are live:

| Line | Livewire | Status |
| --- | --- | --- |
| `2.x` | 4.x | current — where features land |
| `1.x` | 3.x | maintained — fixes only |

There is no release that runs on both Livewire versions, which is the whole
reason 2.0 is a major. Constrain the caret at the line you are on:

```jsonc
// composer.json
"require": {
    "nyoncode/wire-core":   "^2.0",
    "nyoncode/wire-forms":  "^2.0",
    "nyoncode/wire-table":  "^2.0",
    "nyoncode/wire-panels": "^2.0"
}
```

Or take the whole stack as one requirement, which is what the suite package is
for and what keeps the set in step without four lines to remember:

```jsonc
"require": {
    "nyoncode/wire-suite": "^2.0"
}
```

---

## Requirements

| Dependency | Supported |
|------------|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12.61+, 13.12+ |
| Livewire | 4.x |
| Tailwind CSS | 3.x or 4.x (radius and spacing theming needs 4.x — see [Theming → Scope](theming.md#scope)) |
| `nyoncode/laravel-package-toolkit` | ^2.4 |

Confirm your app meets these before upgrading.

---

## Livewire 4 (2.0)

**2.0 requires Livewire 4.** The 1.x line stays on Livewire 3 and keeps receiving
fixes; there is no release that runs on both. Upgrade Livewire first, confirm your
own components still work, then move Wire.

```bash
composer require livewire/livewire:^4.0
php artisan optimize:clear
composer update "nyoncode/wire-*"
```

Livewire's own [upgrade guide](https://livewire.laravel.com/docs/upgrading) covers
your application code. Four of its changes reach into what Wire renders for you,
and only the last one needs anything from you.

**`liveOnBlur()` still means what it meant.** From Livewire 4, `wire:model.blur`
says when the *client* syncs its own state, not when it talks to the server — a
bare `.blur` never reaches the server at all. Wire emits `wire:model.live.blur`
instead, so a field declared `->liveOnBlur()` (or `->validateOnBlur()`, which
turns it on) behaves exactly as before. Nothing to change unless you hand-wrote
`wire:model.blur` in an overridden field view, in which case add `.live`.

**Multiple file uploads append themselves.** Livewire 4 merges a new upload onto
a multiple-file field's existing entries itself, where 3.x replaced them. Wire
used to fill that gap and no longer does. If you wrote the same merge in an
`updated()` hook of your own, remove it — otherwise your existing entries are
counted twice.

**The Livewire endpoints moved.** URLs are now `/livewire-{hash}/…` rather than
`/livewire/…`, where the hash derives from your `APP_KEY`. Firewall rules, CDN
bypass rules and anything else matching that prefix by hand needs updating. Wire's
own asset routes are unaffected — they were never under that prefix.

**Alpine comes from Livewire, still.** Livewire 4 ships Alpine 3.16. As on 3.x, do
not install or start Alpine separately.

---

## Row markup and partial rendering (2.0)

Two changes here. One is opt-in and you can ignore it until you want it; the
other happened to every table and is worth ten minutes of your attention if you
have styled, scripted or tested against the table's own markup.

### Every table's rows are assembled differently

The row body used to be laid out in Blade inside the row loop. It is now
assembled in PHP from markup Blade compiles once per table
(`Support\RowRenderer`, and `Support\CardRenderer` for the stacked cards). The
rendered result is the same markup, with two differences:

- **the per-row morph markers are gone.** Livewire injects an
  `<!--[if BLOCK]><![endif]-->` pair around every `@if` and `@foreach` it
  compiles, and the row loop's own conditionals were emitting 459–999 B of them
  per row — 848–1035 B per row once the whitespace between them is counted, and
  1 347 B per stacked card. Nothing in the DOM depended on them except Livewire's
  own morph;
- **the row's conditional children now carry `wire:key`**, which is what pairs
  them through a morph in place of those markers: `ctx-{key}` on the teleported
  context menu, `sel-{key}` on the selection cell, `exp-{key}` on the sub-row
  expander, and `act-{key}-{name}` on every action button rendered **with** a
  record. A button rendered without one — a header action, a bulk action, the
  empty state — is unchanged.

**What to check.** Anything that walks the row's children by position or counts
comment nodes: a CSS `:nth-child()` that assumed a stable child count, a
`querySelector` chain that stepped over the markers, a browser test asserting on
them. Ordinary selectors — `[data-row-key]`, `[data-testid]`, `[data-column]`,
`tbody tr` — are untouched and remain the supported way in.

**If you published the table views**, this is the one that can bite silently.
`tables/index.blade.php` no longer contains the row body at all: it was split
into `partials/data-region.blade.php`, and the row and card are rendered from
PHP. A published copy from 1.x keeps working — Laravel prefers it — but it keeps
the old cost and none of the new behaviour, and it will not pick up
`rowPartials()`. Re-publish it, or better, delete the copy and configure instead:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

### `rowPartials()` — opt-in, and off by default

A write can answer with the regions it moved rather than re-rendering the table:

```php
$table->rowPartials()
```

On a 25-column, 20-row page an inline cell save costs 49.3 ms and 556 kB as an
ordinary render, and 3.2 ms and 26 kB as one row. Nothing changes for a table
that does not ask for it — no anchor is emitted and no byte is spent.

**What you trade** is that a re-rendered row keeps its position: an edit that
would move the record under the current sort leaves it where it is until the next
full render. On a wide editable grid that is the right trade, which is why it is
opt-in rather than on.

See [Advanced → Row Partials](../table/advanced.md#row-partials) for what a write
answers with on each shape of table, and for how the same anchors serve `poll()`
and `live()`.

---

## The per-user preference store moved into wire-core (2.0)

`TablePreferenceDriver` and the `table_preferences` table are now
`Foundation\Preferences\Contracts\PreferenceDriver` and `wire_preferences`,
in **wire-core**.

```php
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;              // [tl! --]
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;        // [tl! ++]

use NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver;             // [tl! --]
use NyonCode\WireCore\Foundation\Preferences\Drivers\DatabasePreferenceDriver;  // [tl! ++]
```

**Why it moved.** A dashboard layout is the same shape as a table's remembered
columns — a small JSON bag keyed by a surface and a user — and widgets live in
`wire-core`, which `wire-table` depends on, so from a widget the table's store
could not be reached. Writing a second one would have been a second
implementation of one idea.

**Nothing about configuring a table changed.** `config('wire-table.preferences')`
is still where a table's driver is chosen, still with the same aliases and the
same guest fallback. Only the classes the aliases point at moved, and the shipped
config was updated with them.

**Three things to do**, and only if you touched any of them:

1. **Re-publish the migration.** It ships with wire-core now:
   `vendor:publish --tag="wire-core::migrations"`. Running it **renames** an
   existing `table_preferences` to `wire_preferences` and `table_key` to
   `surface_key` — nobody loses a saved layout — and creates the table on a fresh
   installation.
2. **A custom store** implements `PreferenceDriver` instead of
   `TablePreferenceDriver`. The four methods are unchanged; the first argument is
   `$surfaceKey` rather than `$tableKey`, because it is now a table key *or* a
   dashboard key.
3. **A published `config/wire-table.php`** points its `drivers` aliases at the
   old namespaces. Update the three `use` lines.

`Table::preferenceDriver()`, `rememberColumns()` and saved views are untouched,
and `wire-table`'s whole suite passes unchanged — which is the real evidence the
move kept the behaviour.

One small loss, in session storage only: the session key prefix is `wire.`
rather than `wire-table.`, so a layout a user had unsaved in their session resets
once. Database rows are carried across by the migration.

---

## `TableWidget` moved to wire-table, and now renders (2.0)

`WireCore\Widgets\TableWidget` is now `WireTable\Widgets\TableWidget`.

```php
use NyonCode\WireCore\Widgets\TableWidget;    // [tl! --]
use NyonCode\WireTable\Widgets\TableWidget;   // [tl! ++]
```

**The move is why it works.** The old class stored the callback you gave
`->table(...)` and never called it: it rendered a card with a heading and an
empty `<div>`, and `getTableCallback()`'s only caller in the whole framework was
a test asserting the getter. That was structural, not neglect — widgets live in
`wire-core`, the table engine lives in `wire-table`, and table depends on core,
so from core the engine could not be reached.

It draws columns and rows now, each cell through `Column::renderCell()` and the
query planned by `TableQueryService`, so badges, money formats and relation
paths look exactly as they do on a full table.

**Two things to check in your own code.** The callback needs a data source, since
it is really executed now:

```php
TableWidget::make()
    ->limit(5)                                    // [tl! ++]
    ->table(fn (Table $table) => $table
        ->model(Order::class)                     // [tl! ++]
        ->columns([TextColumn::make('reference')]))
```

And a card draws **5 rows** unless `->limit()` says otherwise — there is no
pagination to catch the rest.

**What it deliberately does not have**: toolbar, search, filters, pagination,
bulk actions, row actions. A dashboard card that grows those is a table wearing a
widget costume; put a page with `WithTable` behind it instead.

---

## `Widget::lazy()` defers something now (2.0)

The method survives; what changed is that it does what it says. Before 2.0 no
widget view read the flag — there was no `wire:init`, no intersect directive and
no island behind it — so a widget marked lazy rendered in full like any other.

```php
StatsOverviewWidget::make()->lazy()   // drew everything immediately
StatsOverviewWidget::make()->lazy()   // draws a placeholder, then fetches itself
```

**Nothing to change, but check what you marked.** A call that used to be inert is
now a deferral: the grid renders a skeleton card, `wire:init` calls
`loadWidget()` on the host, and the response carries that widget's markup as a
`wire:partial` region. Drop the call from any widget that is not actually slow —
the framework's rule is to make a render cheap rather than to defer it, and eager
HTML costs no open latency.

**What made it possible.** Per-widget deferral needs a region the server can
name, and an `@island` cannot be one inside a `@foreach`: Blade emits one island
body per directive occurrence and the extracted body never receives the loop
variable. A partial is a plain attribute chosen by the server, which is why
polling could already answer one widget's tick with one widget. Deferral is the
same mechanism with a different trigger.

Component-level lazy still works and is still the right tool for a whole grid:
`<livewire:my-dashboard lazy />`.

See [Widgets → Deferred Rendering](../core/widgets/index.md#deferred-rendering).

---

## `ChartWidget::filter()` resolves on the server, and every widget has one (2.0)

The filter dropdown used to be resolved in the browser: a `<select>` bound to an
Alpine property, and an `updateChart()` that assigned `this.labels` and
`this.datasets` back onto the chart it was constructed with. Changing the
selection therefore redrew the identical chart, and the dataset closure was never
called with anything but its default.

The selection now round-trips to the host, which re-resolves the closures and
answers with that widget alone.

```php
ChartWidget::make()
    ->filter(['week' => 'This week', 'month' => 'This month'])
    ->datasets(fn (?string $filter) => $this->revenue($filter))   // now actually re-runs
```

**Nothing to change in your code.** Two things are worth knowing:

- The closure now runs on a Livewire request rather than once per page render, so
  it must be safe to call repeatedly — it always was, but nothing exercised it.
- `filter()`, `activeFilter()`, `getFilterOptions()`, `hasFilter()` and
  `getActiveFilter()` moved from `ChartWidget` to the widget base
  (`HasWidgetFilter`). Every widget has them now. Nothing moved out of reach; a
  chart still answers the same calls.

**If you overrode `widgets/chart.blade.php`, re-publish it.** Three things in it
changed and the view is where all three meet:

- the `<select>` is a shared partial (`widgets.partials.widget-filter`);
- the wrapper carries a `wire:key` that includes the active filter. That key is
  load-bearing — Alpine never re-evaluates `x-data` on an element it has already
  initialised, so without it the morph patches the attribute and Chart.js keeps
  drawing the old series;
- the `wireChart` Alpine factory takes **four** arguments now, not six.
  `filterOptions` and `activeFilter` are gone from it, because nothing in the
  browser decides the filter any more, and `updateChart()` went with them — it
  was the method that assigned the same two arrays back onto the chart.

```blade
x-data="wireChart(@js($type), @js($labels), @js($datasets), @js($filterOptions), @js($activeFilter), @js($options))"  {{-- [tl! --] --}}
x-data="wireChart(@js($type), @js($labels), @js($datasets), @js($options))"  {{-- [tl! ++] --}}
```

A published view left on the old call passes `$options` where the factory now
reads `$filterOptions`, so the chart is built with no options at all — it draws,
and it draws wrong. Nothing warns about it, which is why it is worth the two
minutes.

---

## `InvalidChartDataException::notChartItems()` is now `InvalidWidgetDataException` (2.0)

`items()` stopped being a chart feature: bar charts, progress widgets and list
widgets all take a series through one owner (`HasWidgetItems`), and one of them
throwing an exception named after charts would be a name that lies.

```php
catch (InvalidChartDataException $e)    // [tl! --]
catch (InvalidWidgetDataException $e)   // [tl! ++]
```

Both extend `InvalidArgumentException` and both implement `WireException`, so a
`catch` on either of those is unaffected. `InvalidChartDataException` still exists
and is still thrown for an unknown chart type, an unknown variant and an
out-of-range item percentage.

While the owner moved, `items()` gained a closure form on every widget that draws
a series — `->items(fn (?string $filter) => …)` — which is what makes a filter on
a bar chart mean anything.

---

## Field views: the Alpine body moved into a bundle (2.0)

Seven field types used to inline their whole Alpine controller into the markup as
an `x-data` object literal, so a page with six date pickers sent the same few
hundred lines six times. The bodies are registered `Alpine.data()` factories now.

**Nothing to do unless you override one of these field views**: `DateTimePicker`,
`TimePicker`, `Select` (the searchable combobox), `Tags`, `Rating`, `RichEditor`,
`MarkdownEditor`. If you do, the `x-data` you copied is gone — call the factory
with a config object instead:

```blade
{{-- before --}}
<div x-data="{ open: false, value: $wire.entangle('data.at'), hasDate: true, /* …300 lines… */ }">   {{-- [tl! --] --}}

{{-- after --}}
<div x-data="wireDateTimePicker({                    {{-- [tl! ++:4] --}}
    state: $wire.entangle('data.at'),
    hasDate: true,
    typeable: true,
})">
```

Two things stay in the markup on purpose. **`state`**, because `$wire.entangle`
and `@entangle` are Alpine *magics* and are in scope only inside an `x-data`
expression — it cannot move into the bundle. And any **server-side string** the
controller needs, such as a translated `prompt()` title, which arrives as config.

A third rule bites if you are porting your own field: a Blade `@if` inside the
body has to become a runtime branch. A factory is compiled once and shared by
every instance, so nothing can vary the *shape* of the object any more — only its
behaviour.

The controllers ship in `wire-forms-fields.js`, and the searchable-select
combobox in `wire-core-dropdown.js` (it is core's: seven surfaces across forms and
table include that partial). Both are registrars, so they load with the document
rather than on request. Each converted view also includes
`wire-forms::partials.field-assets`, because
[`@wireStackScripts`](getting-started.md#javascript-assets) is additive — an app
that never adds the directive still has to get the controller, or the `x-data`
evaluates against an empty registry and the field silently does nothing.

---

## Removed: every shim marked for 2.0 (2.0)

1.x carried a set of methods and classes whose docblock said *"Will be removed in
v2.0"*. This is that release, so they are gone — calling one now raises
`BadMethodCallException` (or a class-not-found) instead of logging a deprecation.
The replacements have existed for the whole 1.x line, and each is a rename:

| Removed | Use instead |
| --- | --- |
| `Action::hiddeLabel()` | `Action::hideLabel()` — the old name was a typo |
| `ActionHalt::modalHeading()` | `ActionHalt::heading()` |
| `ActionHalt::modalDescription()` | `ActionHalt::description()` |
| `ActionHalt::body()` | `ActionHalt::description()` — the halt speaks the modal vocabulary now |
| `ActionHalt::modalIcon()` | `ActionHalt::icon()` |
| `ActionHalt::modalSubmitLabel()` | `ActionHalt::submitLabel()` |
| `ActionHalt::modalCancelLabel()` | `ActionHalt::cancelLabel()` |
| `ActionHalt::modalWidth()` | `ActionHalt::width()` |
| `ActionHalt::formValidation()` | `ActionHalt::validation()` |
| `Table::polling()` | `Table::poll()` |
| `TableNotification` | `Notification` |
| `TableNotificationManager` | `NotificationManager` |
| `confirmTableAction()`, `executeConfirmedAction()`, `closeConfirmationModal()`, `confirmBulkAction()`, `getConfirmationModalData()` | the halt modal API — see [Lifecycle And Queues](../core/actions/lifecycle.md#halt-execution) |
| `WireForms\Components\Layout\{Section,Fieldset,Grid}` | `WireCore\Foundation\Schema\{Section,Fieldset,Grid}` |

Five more were deprecated during 1.x without naming a release, and go in the same
sweep — each is a rename with the same behaviour behind it:

| Removed | Use instead |
| --- | --- |
| `Table::rowContextMenu([...])` | `Table::recordActions([Action::make('edit')->onContextMenu(), …])` |
| `TextInputColumn::formatForSave()` | `dehydrateState()` |
| `TextInputColumn::formatAfterLoad()` | `hydrateState()` |
| `Table::flattenSubRows()` / `isFlattenSubRows()` | `subRowsDefaultExpanded()` / `isSubRowsDefaultExpanded()` |
| `toggleFlattenMode()` | `toggleAllRowExpansion()` |
| the legacy magic properties (`$this->tableSearch`, `$tableFilters`, `$flattenMode`, …) | `$this->tableState->get('search')` / `->set(...)`, or `Table::queryString()` for URL state |
| `TableQueryingPayload::$forceSortColumn` / `$forceSortDirection` | the array `table.querying` hook's `force_sort_column` key |

**The legacy properties are the one to check for.** `WithTable` used to answer
`$this->tableSearch` and twenty siblings through `__get`/`__set`, mapping each to
a state path. They are gone, so a component reading one now gets Livewire's
"property does not exist" — including a `$queryString` array naming them, which is
what the docs used to show for URL state. The supported way is
[`Table::queryString()`](../table/advanced.md#url-state-persistence), which
validates what it reads back:

```php
protected $queryString = ['tableSearch' => ['as' => 'q']];   // [tl! --]
public function table(Table $table): Table                    // [tl! ++]
{                                                             // [tl! ++]
    return $table->queryString();                             // [tl! ++]
}                                                             // [tl! ++]
```

**Binding the context menu as a record action makes the table a grid.** That is
the point of the replacement — the menu becomes reachable from the keyboard — but
it means every row carries the role and tabindex the keyboard layer needs, about
260 bytes more per row than the mouse-only list. An `ActionGroup` in the menu list
is no longer accepted either: bind its actions one at a time, which renders the
same items.

**The `modal*()` rows are about `ActionHalt` and nothing else.** An *action* still
has `modalHeading()`, `modalDescription()`, `modalWidth()` and the rest — those
are canonical and unchanged. It was only the halt object that carried them as
aliases of its own shorter vocabulary:

```php
$action->halt()
    ->modalHeading('Warnings detected')          // [tl! --]
    ->modalDescription('Continue anyway?');      // [tl! --]
    ->heading('Warnings detected')               // [tl! ++]
    ->description('Continue anyway?');           // [tl! ++]
```

**The five confirmation methods were already no-ops.** They logged a deprecation
and returned; the halt modal has executed through `*WithData()` since the frame
stack landed. Removing them cannot change behaviour — it only turns a silent
no-op into a loud error, which is what a call site that still uses them deserves.

**The form layout subclasses are the one removal that changes what renders**, and
it changes it for the better. `WireForms\Components\Layout\Section` existed only
to swap in a form-specific copy of the section view, and that copy had fallen
behind the canonical one: no header actions, no `aside()`, its own column map. A
form section built from `Foundation\Schema\Section` gets all three, plus the
surface background an infolist section already had:

```php
use NyonCode\WireForms\Components\Layout\Section;   // [tl! --]
use NyonCode\WireCore\Foundation\Schema\Section;    // [tl! ++]
```

Nothing else about them changes — same class, same fluent API, same nesting.

---

## Deprecated trait shims are gone (2.0)

The nine trait aliases under `NyonCode\WireCore\Concerns\` were removed. Each
was a `class_alias()` shim carrying `@deprecated … Will be removed in v2.0`, and
this is that release.

Every one of them pointed at the trait of the same name under
`Actions\Concerns\`, so the migration is the import line and nothing else:

```php
use NyonCode\WireCore\Concerns\HasIcons;          // [tl! --]
use NyonCode\WireCore\Actions\Concerns\HasIcons;  // [tl! ++]
```

The nine names: `HasButtonStyles`, `HasColor`, `HasDynamicProperties`,
`HasIcons`, `HasKeyboardShortcut`, `HasLifecycle`, `HasLoadingState`,
`HasModal`, `HasVisibility`.

The traits themselves are untouched — same methods, same behaviour. If you never
imported from `WireCore\Concerns\`, there is nothing to do.

Alongside them, `NyonCode\WireTable\Concerns\TableQueryService` — the same kind
of alias, left behind when that class moved to `Services\`, and carrying the same
`Will be removed in v2.0` note:

```php
use NyonCode\WireTable\Concerns\TableQueryService;   // [tl! --]
use NyonCode\WireTable\Services\TableQueryService;   // [tl! ++]
```

Nothing in the docs asks you to construct it, so this is unlikely to reach you.

**One exception worth taking.** For colors, prefer
`Foundation\Concerns\HasColor`: it is the canonical owner, and
`Actions\Concerns\HasColor` is itself only a thin alias of it.

---

## A table can read from something other than Eloquent (2.0)

Nothing you have written changes. `->model()` and `->query()` behave exactly as
before, and every action closure keeps its `Model $record`.

What is new is that a table now reads through a `DataSource`, so it can be given
rows that are not in a database:

```php
use NyonCode\WireTable\Data\CollectionDataSource;

$table->dataSource(new CollectionDataSource([         // [tl! focus]
    ['id' => 1, 'name' => 'Ada', 'score' => 90],      // [tl! focus]
]));                                                  // [tl! focus]
```

Such a table is a **restricted** table: a source declares what it can answer, and
asking for something it declined raises `UnsupportedQueryAspectException` rather
than quietly returning rows that ignored half the query. For a collection that
means no raw SQL expressions, no relation paths, no subquery aggregates and no
cursor paging.

See [Data Sources](../table/data-sources.md) for the whole surface. If you only use
Eloquent tables, there is nothing to do.

---

## Dependency floors (1.17)

**Laravel 10 and 11 are gone.** 1.17 moved the JavaScript bundles from a package
route to real files under `public/vendor`, and the code that mirrors them lives in
`nyoncode/laravel-package-toolkit` — next to the `hasAssets()` declaration and the
publish tag it is the read side of. The toolkit is on `illuminate/support ^12.61.1|^13.12.0`,
and a dependency's floor is your floor: an app below it cannot resolve the Wire
packages, whatever the `^12.0` in their own `composer.json` says. Upgrade Laravel
first, then Wire.

**The toolkit constraint is `^2.4`.** You do not require it directly, so in the
normal case `composer update "nyoncode/wire-*"` moves it with everything else and
there is nothing to do. It only becomes visible in two shapes:

- your `composer.json` names `nyoncode/laravel-package-toolkit` — from building
  your own package on it, or from an old pin — and holds it below 2.4. Composer
  reports the Wire packages as uninstallable rather than the toolkit as too old,
  so widen that constraint to `^2.4` first.
- you run Octane. The per-worker asset memo is flushed on `RequestTerminated`
  through the toolkit's `PublishedAssets::flush()`, which 2.4 is the first release
  to carry. Below it, a worker that survives a deploy keeps emitting the previous
  release's `?id=<mtime>` and `wire:navigate` never notices the new bundles.

---

## Upgrade Steps

1. **Read the changelog.** Check `CHANGELOG.md` for the versions you are crossing,
   especially any **Breaking Changes** section.

2. **Update the packages.**

   ```bash
   composer update "nyoncode/wire-*"
   ```

3. **Re-check published files.** If you published config, views, or translations,
   your copies do **not** update automatically. Diff them against the new package
   versions and merge any relevant changes:

   - `config/wire-*.php`
   - `resources/views/vendor/wire-*/…`
   - `lang/vendor/wire-*/…`

   The fewer views you override, the less there is to reconcile here — see
   [Theming → Overriding Views](theming.md#overriding-views).

4. **Clear caches and rebuild assets.**

   ```bash
   php artisan view:clear
   php artisan config:clear
   npm run build
   ```

5. **Run your test suite.** A [test suite](testing.md) is the fastest way to catch
   a breaking change in your own forms and tables.

---

## Registration, routing and the menu

One seam replaced three direct reads: the menu, the router and the ⌘K palette all
read a [`Catalog`](../panels/navigation.md#catalog-api) now, so registering something
once reaches all three. Four names moved with it, and none of them kept an alias
— this line has not shipped, and a compatibility shim for a rename nobody has
depended on is cost without a reader.

| Before | Now |
| --- | --- |
| `Core\Resources\Contracts\NavigationSource` | `Foundation\Registration\Contracts\RegistrySource` |
| `NavigationSource::navigableClasses()` | `RegistrySource::registeredClasses()` |
| `WirePanels\Resources\Contracts\ProvidesResourcePages` | `WireCore\Foundation\Routing\Contracts\ProvidesPages` |
| `WirePanels\Resources\Contracts\ConfiguresResourceRoutes` | `WireCore\Foundation\Routing\Contracts\ConfiguresRoutes` |
| `WirePanels\Routing\RoutePage` | `WireCore\Foundation\Routing\RoutePage` |

The three routing names moved **down**, into `wire-core`, so that a `Dashboard`
can declare pages too — a dashboard is routed by `Route::wireResources()` like any
resource now. The URL convention did not move: `ResourceRoutes`, the URL shape and
the `wire.{key}.{page}` names are still `wire-panels`.

Two constructors changed, and both are resolved from the container, so only code
that built them by hand is affected: `Workspace` takes a `Catalog`, its navigation
groups and a `ResolvesPageUrls`; `GlobalSearch` takes a `Catalog` instead of a
`ResourceRegistry`.

**What you can now delete.** A menu entry carries the URL of its key's page and a
search result carries the URL of its record, both filled from the key, so the
hand-written `key => url` map every application kept can go:

```php
// before — a map beside the routes, and the copy that drifts
<a href="{{ $urls[$key] }}">                                  // [tl! --]
url: route('orders.show', $record),                           // [tl! --]

// now
<a @if($item->getUrl()) href="{{ $item->getUrl() }}" @endif>  // [tl! ++]
// toGlobalSearchResult() passes no url at all                   [tl! ++]
```

An explicit `url:` or `->url()` still wins, for a row that goes somewhere the
convention does not reach.

**Routing is still opt-in**, and `Route::wireResources()` in your own route file
is still the reference path. What is new beside it is
[`wire-panels.routes`](configuration.md#panels) — the same group arguments handed
over once — and [zones](../panels/routing.md#zones), several mount points over one
catalogue. Both are off until you turn them on.

---

## Selection and keyboard gestures

A table's selection grew from a column of checkboxes into a full gesture surface
(see [Selecting Rows](../table/selection.md)). Four things to check on the way up.

**1. Every row gesture is opt-in — `->gestures()`.** The selection grew a full
gesture surface: `Shift`/`mod` clicks for ranges, a drag down the checkbox column
that sweeps a block in, and from the keyboard the arrows, `Space`,
`Shift`+arrows and `mod`+`A`. None of it is on unless a table asks, because each
changes how the table answers a visitor who never meant to operate it — the rows
go into the tab order, an active row is marked, a drag starts selecting, and a
modified click stops meaning a click.

Add one call to the tables that want it:

```php
->gestures()
->selectable()
```

or, for a project where every table is a back-office table:

```php
// config/wire-table.php
'defaults' => ['gestures' => true],
```

What is *not* affected: the checkboxes, both select-all controls and the bulk bar
work with no change on your side, and a table that never asked mounts no
delegated controller at all. So do the right-click row menu and the fill handle,
each of which you already had to ask for. See [The Gesture Layer](../table/gestures.md) for the six capabilities and how
to mix them.

**2. `->onKey()` on a navigation key now throws.** It used to be dropped
silently, so the action simply never fired. If a table binds one of these, the
binding was already dead code — rebind it to a free key:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`Backspace` stays available, and now doubles as an alias of `Delete`.

**3. Range gestures no longer leave "all matching" mode.** When a selection is
"everything the filter matches", the stored list is the set of *exclusions* — so
a `Shift`+arrow range over it now **deselects** that range instead of collapsing
the whole selection down to one page. If your code reads the selection directly,
note that `getSelectedRecordKeys()` returns `[]` in that mode by design; use
`selectedRecordsQuery()` or `eachSelectedRecord()` instead.

**4. Republish the table view if you have overridden it.** The gestures need
markup the packaged JavaScript looks for, and a published copy of
`resources/views/vendor/wire-table/tables/index.blade.php` will not have it. The
view carries a contract marker so a stale copy fails loudly in the browser
console rather than selecting the wrong rows in silence:

```bash
php artisan vendor:publish --tag=wire-table::views --force
```

Re-apply your customisations on top of the new file. If you overrode the view
only to restyle it, [Theming](theming.md) is usually the smaller path.

**5. Behaviour-only record actions now render as buttons on a mobile card.** A
phone has no double click, no right click and no hover to discover either, so an
action bound only to a gesture used to be unreachable once the table stacked.
It is now rendered as an ordinary button on the card — and only there; the
desktop table is unchanged. Nothing is doubled: an action already in
`->actions()`, or one promoted with `->alsoInRowActions()`, still yields exactly
one button, and the fallback buttons count towards
`->collapseActionsOnMobile()`. Opt out per table:

```php
->recordActionButtonsOnMobile(false)
```

---

## JavaScript assets

Wire's Alpine controllers are now declared by each package and can be emitted from
one place in your layout. Two things to do on the way up.

**1. Add `@wireStackScripts` to the layout `<head>`.**

```blade
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @wireStackScripts {{-- [tl! focus] --}}
</head>
```

It is additive — every surface still loads its own bundle, so an app without the
directive keeps working. But it is what fixes components dying after a
`wire:navigate` visit (`wireRecordSelection is not defined`, dead dropdowns, a grey
scrim over the table): Livewire's cached Back/Forward path does not wait for newly
injected `<head>` scripts, and only a bundle that was already in the document is
immune. See
[Getting Started → JavaScript Assets](getting-started.md#javascript-assets).

If your app previously worked around this by `@include`-ing package partials in its
layout, delete those includes and use the directive instead — the partial paths are
internal and the directive dedupes with them anyway.

**2. `window.Sortable` is no longer provided.** SortableJS is compiled into the
`wire-sortable` bundle, so `config('wire-sortable.sortablejs_cdn')` now defaults to
`null` and no CDN script is loaded. Reordering is unaffected — the drag controller
uses the bundled copy and never reads the global.

Only **your own** code is affected, if it relied on that global existing. Either ask
for the script back:

```php
// config/wire-sortable.php
'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
```

or bundle SortableJS yourself:

```js
// resources/js/app.js
import Sortable from 'sortablejs';
window.Sortable = Sortable;
```

Nothing else changes: the config key still works when set, and applications that
already set it are unaffected.

---

## Finding Breaking Changes

`CHANGELOG.md` is the source of truth. Breaking changes are called out under a
**Breaking Changes** heading per release, often with a before/after migration
table. For example, the `0.1.0` release moved actions and notifications from
`NyonCode\WireTable\…` to `NyonCode\WireCore\…`; the changelog lists each moved
class so you can update `use` statements with a find-and-replace.

If a class or method referenced in these docs no longer exists after an upgrade,
it was likely moved or renamed — search `CHANGELOG.md` for the old name.

---

## See Also

- [Getting Started](getting-started.md) — requirements and install
- [Configuration](configuration.md) — publishable config
- [Theming](theming.md) — keeping view overrides minimal
- [Troubleshooting](troubleshooting.md) — issues that appear after an update

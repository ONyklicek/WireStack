---
order: 100
---

# Upgrade Guide

How to move between Wire versions safely and where to find breaking changes.

---

## Versioning

The Wire ecosystem ships as four packages — `wire-core`, `wire-forms`,
`wire-table`, `wire-sortable` — released together from one monorepo, so their
versions move in lockstep. Install or constrain them as a set.

Wire is currently in the **`0.x`** line. Per common pre-1.0 convention, minor
releases may contain breaking changes, so pin a version you have tested and read
the changelog before bumping:

```jsonc
// composer.json
"require": {
    "nyoncode/wire-core":     "^0.1",
    "nyoncode/wire-forms":    "^0.1",
    "nyoncode/wire-table":    "^0.1",
    "nyoncode/wire-sortable": "^0.1"
}
```

---

## Requirements

| Dependency | Supported |
|------------|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Laravel | 12.61+, 13.12+ |
| Livewire | 4.x |
| Tailwind CSS | 3.x or 4.x |
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

See [Advanced → Row Partials](table/advanced.md#row-partials) for what a write
answers with on each shape of table, and for how the same anchors serve `poll()`
and `live()`.

---

## `Widget::lazy()` is gone (2.0)

`Widget::lazy()` and `Widget::isLazy()` were removed. They never deferred
anything: no widget view read the flag — there was no `wire:init`, no intersect
directive and no island behind it — so a widget marked lazy rendered in full like
any other.

```php
StatsOverviewWidget::make()->lazy()   // [tl! --]
StatsOverviewWidget::make()           // [tl! ++]
```

Deleting the calls is the whole migration; nothing rendered differently before.

**If you actually want deferral**, defer at the component level rather than the
widget level — a dashboard is one Livewire component, and a widget is markup
inside it, not a component of its own. `<livewire:my-dashboard lazy />` defers the
whole grid. Per-widget deferral is not available: it would need an island per
widget, and an `@island` inside a `@foreach` does not compile — Blade emits one
island body per directive occurrence and the extracted body never receives the
loop variable.

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

See [Data Sources](table/data-sources.md) for the whole surface. If you only use
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

## Selection and keyboard gestures

A table's selection grew from a column of checkboxes into a full gesture surface
(see [Selecting Rows](table/selection.md)). Four things to check on the way up.

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
each of which you already had to ask for. See [The Gesture Layer](table/gestures.md) for the six capabilities and how
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

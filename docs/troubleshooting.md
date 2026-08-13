---
order: 90
---

# Troubleshooting

Common issues and their fixes. Most are configuration mismatches in the host
Laravel app rather than bugs in Wire.

---

## Buttons and inputs are invisible

**Symptom:** Buttons, badges, and focus rings render as white-on-transparent or
vanish entirely.

**Cause:** The `primary` Tailwind color is not defined. Wire uses `primary` for
every accent, so without it those elements have no color.

**Fix:** Define `primary` in your Tailwind config — see
[Getting Started → Primary Color](getting-started.md#primary-color) and
[Theming → Colors](theming.md#colors).

---

## Components render unstyled

**Symptom:** Markup appears but with no Tailwind styling.

**Cause:** Tailwind is not scanning the Wire vendor views, so their classes are
purged from the build.

**Fix:** Add the Wire view paths to your Tailwind `content` array:

```js
content: [
    './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-core/src/**/*.php',
    './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-forms/src/**/*.php',
    './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-table/src/**/*.php',
    './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-sortable/src/**/*.php',
],
```

The `src/**/*.php` paths matter: some components compose their utility classes
in PHP (positioning, width, height), so scanning only the Blade views leaves
those classes purged. On Tailwind 4, add the matching `@source` lines for both
`resources/views` **and** `src` (see the getting-started guide).

Rebuild assets afterward (`npm run build` or `npm run dev`).

---

## Slide-over is anchored left, overflows, or won't scroll

**Symptom:** An action's slide-over (`->slideOver()`) appears pinned to the
**left** with the dimmed page on the right, its content **overflows** past the
viewport, the footer sits **off-screen at the bottom**, and the body won't
scroll.

**Cause:** The slide-over composes its positioning, width and height utilities
in PHP (`SlideOverComponent`), not in a Blade file — classes like `sm:right-0`,
`sm:pl-10`, `sm:h-full`, `max-h-[85vh]` and `sm:max-w-2xl`. If Tailwind only
scans `resources/views` and not the package `src`, those classes are purged:
without `sm:right-0` the panel falls to the left, and without the height
utilities it is never height-constrained, so it overflows instead of scrolling
its body.

**Fix:** Add the `src/**/*.php` paths (Tailwind 3) or the matching `@source`
lines (Tailwind 4) as shown in [Components render unstyled](#components-render-unstyled),
then rebuild. See also the getting-started guide.

---

## "No publishable resources for tag"

**Symptom:** `vendor:publish` reports no resources for a tag like
`wire-forms-config`.

**Cause:** Wire's publish tags use a `::` separator, not a dash.

**Fix:** Use the correct tag format:

```bash
php artisan vendor:publish --tag=wire-forms::config   # ✅
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-forms::translations
```

The groups are `config`, `views`, `translations`, and (where applicable)
`migrations` — each prefixed with the package short name and `::`.

---

## Alpine errors or components behaving twice

**Symptom:** Console errors about Alpine being initialized twice, or directives
firing twice.

**Cause:** Alpine was installed and started separately. Livewire 4 already ships
and starts Alpine.

**Fix:** Remove any standalone Alpine install and `Alpine.start()` call. Let
Livewire provide it.

---

## `wireX is not defined` after a `wire:navigate` visit

**Symptom:** A page works when loaded directly, but reaching it through
`wire:navigate` — or with the browser's Back/Forward buttons — leaves the table
or form inert. The console shows a `ReferenceError` naming a Wire component
(`wireRecordSelection`, `wireDropdown`, `wireSortable`, …). The loudest version
is a full-page grey scrim over a dead table: every mobile sheet backdrop is bound
to state that was never created.

**Cause:** The bundle that defines the component arrived *with* the new page, and
Livewire's cached Back/Forward path does not wait for newly injected `<head>`
scripts before it initialises Alpine on the swapped-in markup.

**Fix:** Put the directive in your layout `<head>` so the controllers are in the
document from the first page load, where nothing can outrun them:

```blade
<head>
    @wireStackScripts
</head>
```

See [Getting Started → JavaScript Assets](getting-started.md#javascript-assets).

---

## JavaScript 404s and `wireX is not defined`

**Symptom:** The same `ReferenceError` as the previous entry, but on *every* page
and however you reached it — a hard reload shows it too. The network tab has 404s on
`/wire-core/assets/dropdown.js`, `/wire-table/assets/records.js`, or a sibling
under `/wire-forms/…` or `/wire-sortable/…`.

**Cause:** Two things went wrong together. The packages normally copy their bundles
into `public/vendor/<package>` and emit *those* paths, so nothing hits PHP — a
`/wire-core/assets/…` URL in your markup means that copy could not be made and the
package route is standing in for it. And your web server is answering the route
itself instead of forwarding it to PHP. The stock Laravel nginx config sends
anything not on disk to `index.php`, but a config with a static-asset block does not:

```nginx
location ~* \.(js|css)$ {
    try_files $uri =404;      # a route is not a file on disk → 404, PHP never sees it
}
```

Nothing here is package-specific: the same block 404s Livewire's own
`/livewire/livewire.js`.

**Fix — make `public/` writable, or write it at build time.** The usual cause is a
`public/` the web user cannot write to, or a read-only container. Either grant the
write, or do the copy while the filesystem still is writable:

```bash
php artisan vendor:publish --tag=laravel-assets --force
```

**Or make the route reachable**, by letting the block fall through to the front
controller — the right answer where a writable `public/` is genuinely not on offer:

```nginx
location ~* \.(js|css)$ {
    try_files $uri /index.php?$query_string;   // [tl! focus]
}
```

A related warning, when copies exist but could not be refreshed after an upgrade:

```text
wireStack: the published copies of wire-core/dropdown are older than the bundles
the packages ship, and are what this page just loaded.
```

The page still works — an old bundle beats no bundle — but the same writability
problem is behind it. See
[Getting Started → JavaScript Assets](getting-started.md#javascript-assets).

---

## Reordering stops working, or my own code loses `window.Sortable`

**Symptom:** After upgrading, your application's own JavaScript throws
`Sortable is not defined`.

**Cause:** SortableJS is now compiled into the `wire-sortable` bundle, and
`config('wire-sortable.sortablejs_cdn')` defaults to `null` — so the CDN script
that used to leave a global behind is no longer loaded. Wire's own reordering is
unaffected; the drag controller uses the bundled copy.

**Fix:** If your code needs the global, ask for it — either set the config key
back to a CDN URL, or `npm install sortablejs` and assign `window.Sortable`
yourself. See
[Sortable Installation → SortableJS](sortable/installation.md#sortablejs).

---

## A field's JavaScript does not run inside a modal

**Symptom:** A JS-backed field works on a normal page but is dead when opened in
a modal or after a Livewire update.

**Cause:** A plain `<script>` tag injected through Livewire's DOM morphing never
executes.

**Fix:** Load the script with Livewire's `@assets` directive (the built-in
`TiptapEditor` does this). If you are building a custom JS field, follow the same
pattern — see
[Extending Forms → JS-Backed Fields](forms/custom-fields.md#js-backed-fields).

---

## `save()` throws with no model

**Symptom:** Calling `$form->save()` throws.

**Cause:** The form has no model (`model(null)` or none set), so there is nothing
to persist.

**Fix:** Either set a model (`->model(User::class)` to create,
`->model($user)` to update), provide custom persistence with
`->using(...)`, or call `->validate()` instead when you only need the data. See
[Model Modes](forms/overview.md#model-modes).

---

## Notifications / toasts don't appear

**Symptom:** Actions succeed but no toast or notification shows.

**Cause:** The notifications container is missing from the layout.

**Fix:** Add it once near the end of `<body>`:

```blade
<x-wire-notifications::toast-container />
```

See the layout in [Getting Started](getting-started.md#layout-template) and
[Core → Notifications](core/notifications.md).

---

## Validation errors don't display

**Symptom:** Validation fails (the save is blocked) but no message renders under
the field.

**Cause:** The form's `statePath` does not match the component property holding
the state, so error keys and field paths diverge.

**Fix:** Ensure `->statePath('data')` matches a public property (`public array
$data = []`) and that you render the form with `{{ $this->form }}`. Field errors
are keyed by the full state path (for example `data.email`) — assert on that path
in tests.

---

## Still stuck?

- Re-read the package-specific doc for the feature (Forms, Table, Sortable, Core).
- For runtime/state issues, run the Integration suite to see if the behavior is
  reproduced: `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`.
- Check the [Upgrade guide](upgrade.md) if the problem started after updating.

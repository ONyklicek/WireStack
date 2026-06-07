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
    './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
],
```

Rebuild assets afterward (`npm run build` or `npm run dev`).

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

**Cause:** Alpine was installed and started separately. Livewire 3 already ships
and starts Alpine.

**Fix:** Remove any standalone Alpine install and `Alpine.start()` call. Let
Livewire provide it.

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

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
| Laravel | 10, 11, 12 |
| Livewire | 3.x |
| Tailwind CSS | 3.x or 4.x |

Confirm your app meets these before upgrading.

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

**1. Keyboard navigation and the drag sweep are opt-in — `->gestures()`.** The
selection grew a full gesture surface: arrow keys walking the rows, `Space`,
`Shift`+arrows, `mod`+`A`, and a drag down the checkbox column that sweeps a
block in. None of it is on unless a table asks, because both of those change how
the table answers a visitor who never meant to operate it — the rows go into the
tab order, an active row is marked, and a drag starts selecting.

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

What is *not* affected: the checkboxes, both select-all controls, the bulk bar,
and `Shift`/`mod`+click ranges all work with no change on your side. So does the
right-click row menu and the fill handle, each of which you already had to ask
for. See [The Gesture Layer](table/gestures.md) for the six capabilities and how
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

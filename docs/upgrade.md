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

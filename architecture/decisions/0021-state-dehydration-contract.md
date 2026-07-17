# ADR 0021: State Dehydration Contract (the write-path seam)

## Status

ACCEPTED — implemented 2026-07-15.

Unblocks `DateTimePicker::format()` / `::timezone()` and retires two ad-hoc
implementations of the same idea. Sibling of the read-path seam that already
exists (`getStateType()` → `StateHydrator`).

## Context

**A field can shape its value on the way in, but not on the way out.**

The read path has a seam. A component declares `getStateType()` and
`StateHydrator` casts the model value into state:

```
model → StateHydrator (`date:Y-m-d`) → Livewire state → widget
```

The write path has none. `Dehydrator::dehydrateAttribute()`
(`packages/core/src/Core/Hydration/Dehydrator.php:42`) resolves the **model's**
cast and reverse-transforms — the field is never consulted. So state is whatever
the widget produced, and that is what gets persisted.

Two consumers already needed the missing hook and each invented its own:

| Where | How | Problem |
|---|---|---|
| `SaveHandler::storeFileUploads()` (`SaveHandler.php:167`, step 2.5) | walks the schema for `FileUpload` **by class** and rewrites `$data[$name]` | hardcodes one field type into the generic save lifecycle |
| `WithTable::updateTableCell()` (`WithTable.php:3038` and `:3105`) | `method_exists($column, 'formatForSave')` duck-typing, called twice | an unnamed, undiscoverable protocol |

Both are the same concept: **let the component transform its state before it is
written**. Per the canonical-owner rule this should be one abstraction in the
lowest layer that can own it, not a per-package variant.

The gap is not academic. Three shipped setters are dead purely because there is
nowhere for them to act:

- `DateTimePicker::format()` — the format to store. It cannot drive
  `getStateType()`, because state *is* the widget's value and the widget parses
  only its own formats (a native `<input type="date">` demands `Y-m-d`; the
  custom picker parses `Y-m-d`, `Y-m-d\TH:i`, `H:i`). A free-form `d.m.Y` would
  break the input it is bound to.
- `DateTimePicker::timezone()` — worse than dead if done by halves. Converting
  only on hydration would put state in the target zone while Eloquent still reads
  it back in the app zone on save: **a silent time shift**, i.e. data corruption.
  It is only safe when both directions exist.
- `RichEditor` / `TiptapEditor::fileAttachmentsDirectory()` were removed rather
  than wired; had this seam existed, "store the attachment, persist the path"
  would have had an obvious home next to `FileUpload`.

## Decision

Introduce **one contract in core**, consumed by both hosts, and express the
existing ad-hoc transforms through it. Shipped as two interfaces rather than one
(`DehydratesState` + `HydratesState`): most components need a single direction —
a `FileUpload` stores on save but loads a plain path — and forcing a no-op
counterpart on them would be noise.

```php
namespace NyonCode\WireCore\Foundation\Contracts;

/**
 * Marks a component that shapes its own value on the way out of the form and
 * into the model — the write-path counterpart of getStateType().
 */
interface DehydratesState
{
    /**
     * Transform the component's state into the value to persist.
     *
     * Receives the record when the host has one (a table cell always does; a
     * create form does not), so a transform may depend on it. Must be pure:
     * the host may call it more than once per save.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed;
}
```

**Hosts consult the contract instead of naming types:**

- `SaveHandler` step 2.5 becomes a schema walk over `DehydratesState`, and
  `FileUpload` implements it (its current body moves in unchanged). The
  hardcoded `collectFileUploadFields()` / `storeFieldFiles()` pair goes away.
- `WithTable::updateTableCell()` replaces the `method_exists(…, 'formatForSave')`
  calls — **three of them, not the two this ADR first counted** — with an
  `instanceof DehydratesState` check; `TextInputColumn::formatForSave()`
  is renamed to `dehydrateState()`. Its documented pipeline (trim → nullable →
  number parsing → case → `beforeSave()`) is unchanged — only its name and the
  way it is discovered.

**`DateTimePicker` then implements it**, and the two dead setters become real:

```php
public function dehydrateState(mixed $state, ?Model $record = null): mixed
{
    if ($state === null || $state === '') {
        return null;
    }

    $zone = $this->hasTimeComponent() ? $this->getTimezone() : null;

    // Both knobs are opt-in — see the BC note below.
    if ($this->format === null && $zone === null) {
        return $state;
    }

    $date = $this->parse($state, $zone ?? config('app.timezone'));
    // …convert back to the app zone when a zone is set, then:
    return $date->format($this->format ?? $this->getStateFormat());
}
```

**Both knobs must be opt-in**, which the first draft of this ADR got wrong. The
sketch above originally formatted with `getFormat()` unconditionally — and
`getFormat()` falls back to `config('wire-forms.date_format')`, `'d.m.Y'` in a
default install. Every field that never called `format()` would have silently
started writing `09.03.2026` into a column that held `2026-03-09`: exactly the
silent corruption this ADR exists to avoid, introduced by the fix for it. A test
caught it before it shipped. Left unset, dehydration returns state untouched.

`timezone()` applies only to `datetime`. A bare date or a time is a wall-clock
value with no instant to move; converting one would shift the day.

with the symmetric read side — the one place the state type string is built:

```php
public function getStateType(): string
{
    // Unchanged: state stays in the widget's parseable format …
}
```

…and a `hydrateState()` counterpart on the same contract for the tz conversion
inbound. **Both directions land in the same PR or neither ships** — a one-sided
timezone is the corruption described above.

## Consequences

**Good**

- One named, discoverable seam replaces a hardcoded class check and a
  `method_exists` protocol.
- `format()` / `timezone()` stop lying; the docs' "Format" section becomes true.
- Future "transform on save" needs (attachments, encryption, money) have a home
  instead of a fourth variant.
- `composer api:dead` shrinks by 2 (`scripts/dead-api-baseline.txt`).

**Costs / risks**

- **Touches the save path of every form and every editable table cell.** The
  blast radius is the reason this is an ADR and not a patch.
- **`method_exists` fails loudly; `instanceof` fails silently.** Swapping the
  duck-typed protocol for the contract quietly disabled the seam it was meant to
  name: `WithTable` (namespace `NyonCode\WireTable\Concerns`) never imported
  `DehydratesState`, so `$column instanceof DehydratesState` resolved to
  `NyonCode\WireTable\Concerns\DehydratesState`, a class that does not exist —
  and `instanceof` against a non-existent class does not autoload or throw, it
  returns `false`. Every editable cell silently stopped trimming, casing, parsing
  numbers and running `beforeSave()`; the raw client string went to the column.
  The old `method_exists($column, 'formatForSave')` had no namespace to get wrong.
  Nothing caught it: PHPStan and Pint are clean on the broken code, the suite was
  green because no test asserted the pipeline *through* `updateTableCell()` (only
  the column's method in isolation), and the swap looked like a pure rename in
  review. **The lesson generalises past this ADR:** an `instanceof` against a
  contract in another package is a silent no-op away from an unimported name, so
  a seam expressed that way needs a test that asserts the *transform happened*,
  not that the save succeeded. `scripts/verify-instanceof-imports.php` now fails
  the build on any unresolvable `instanceof` target across `packages/*/src`.
- `TextInputColumn::formatForSave()` is public — though, checked while writing
  this, **not** documented (`docs/table/columns/text-input.md` never names it; it
  is called only through the `method_exists` protocol). Renaming is still a BC
  break for anyone who found it. Options: a deprecated alias delegating to
  `dehydrateState()`, or a clean break as with `native()`. Recommend the alias:
  unlike `native()` this method *works*, so removing it breaks real behaviour
  rather than a fiction — and it is cheap to keep.
- ~~`dehydrateState()` must be pure — `WithTable` calls its equivalent twice today
  (once without the record, once with). Either keep that contract explicit
  (documented above) or fix the double call as part of the work.~~ **Fixed.** The
  double call stays — it is inherent to the shape of `updateTableCell()`, which
  pre-validates *outside* its transaction and only has a record *inside* it — but
  it no longer **composes**: both passes now dehydrate from the state the client
  sent, never from the output of the earlier one. The distinction was not
  academic. "Pure" does not imply "idempotent under self-composition", and the
  code required the latter: `TextInputColumn` survived by accident (its number
  parsing is guarded by `is_string()`, which a second pass fails), but a user's
  `->beforeSave(fn ($v) => $v + 1)` was applied twice. The contract now promises
  only what a caller can reasonably meet — a pure function of its arguments.
- Timezone correctness needs a round trip through a real column, not just a unit
  test on the two halves. Covered by `DateTimePickerTimezoneRoundTripTest` on the
  MySQL/Postgres matrix (`.github/workflows/database-tests.yml`).

  **This ADR originally claimed SQLite "would happily pass a wrong
  implementation". Measured, it does not** — the one-sided conversion was injected
  deliberately and failed 3 of 6 cases on SQLite, MySQL 8 and Postgres 16 alike.
  The field converts in PHP and stores a plain string, so for a `dateTime` column
  the round trip is driver-independent. The matrix run is still worth its seconds
  — it would catch a driver that coerces on write, as a Postgres `timestamptz`
  would — but the claim that only a real server can catch this was wrong.

**Alternatives rejected**

- *Extend the state-type string* (`date:Y-m-d|Europe/Prague`). Cheap, but grows a
  bespoke string protocol inside `StateHydrator` and still leaves the write path
  unsolved.
- *A `mutateDataBeforeSave` closure per field.* Already exists at form level; a
  per-field closure would put the burden on every caller instead of the component
  that owns the knowledge.
- *Delete `format()` / `timezone()`* like `native()`. Legitimate and cheapest —
  but unlike `native()`, users reasonably expect these to exist on a date picker,
  and the docs have promised them for a long time.

## Verification

- `FileUpload` and `TextInputColumn` behaviour must be **unchanged**: their
  existing suites are the regression gate for the seam swap. **Insufficient as
  written** — those suites call the column's method directly, which is exactly
  why the unimported-`instanceof` break above went unnoticed. A seam needs at
  least one test that drives the *host* and asserts the transform reached the
  column: `WithTableInteractionsTest` now saves through `updateTableCell()` and
  asserts the stored value, not the `success` flag.
- New: a `DehydratesState` contract test (both hosts call it; a component that
  does not implement it is untouched).
- `DateTimePicker`: a round-trip test per mode — model value → state → save →
  model value — asserted against a real DB on the MySQL/Postgres matrix.
  **Done** — `DateTimePickerDatabaseRoundTripTest` drives a real Livewire host
  through fill → save and reads the column back with the Eloquent cast bypassed.
  Run against MariaDB 12.1, MySQL 8.0 and PostgreSQL 16 (6/6 each; the full
  matrix set is 361/361 on all three). The suite is only worth its runtime
  because it was **mutation-checked**: deleting the `setTimezone()` line in
  `dehydrateState()` — the exact one-sided conversion this ADR exists to
  prevent — fails 5 of the 6. Cases that matter: a DST date *and* a
  standard-time date (a hardcoded offset passes one and fails the other),
  repeated saves (drift compounds per round trip), and an app zone that is
  neither UTC nor the field's.
- `composer api:dead` must drop `format()` / `timezone()` from the baseline.
- `composer api:instanceof` guards the seam's discovery mechanism itself.

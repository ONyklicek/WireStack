# Trait Consolidation Audit — 2026-07-23

Multi-thread, adversarially cross-verified audit of code that can be refactored
into shared traits (`HasLabel`/`HasId`-style) to raise reuse and maintainability,
following the canonical-ownership rule in `CLAUDE.md` (one owner per cross-cutting
concern; downstream classes delegate to Foundation concerns instead of re-encoding).

## Method

- **11 line-by-line finders** (one per class family) read every target file in full
  and reported extractable duplication as three kinds: `duplicate-trait` (competing
  traits to merge), `consolidate-into-existing` (class should `use` an existing
  Foundation trait), `new-trait` (repeated shape with no owner yet).
- **62 adversarial verifiers** across two runs re-read the cited lines with a mandate
  to *refute* each claim. Run 1 covered table + forms-base + rich-editors; run 2
  re-verified the six groups whose verifiers hit a session limit (forms-inputs,
  core-actions, core-infolist-entries, cross-package, table-support-services,
  core-ui-surfaces).
- Result: **58 findings → ~45 confirmed, ~17 refuted or rescoped**. The second pass
  was materially stricter and killed several optimistic "delegate to existing"
  claims — that divergence is the point of adversarial review.

## Key architectural finding

The Foundation concerns `HasIcon`, `HasVisibility`, `HasColor`, `HasId` are
**closure-aware and DI-aware**: they call `evaluate()` (via `app()->call()` with
`$get/$set/$state` accessors), use 2-parameter signatures, and `HasId::getId()`
falls back to `getStatePath()`. Plain value classes (modal config, action config,
some entries) want *static* setters and a nullable getter with no state path.

**Consequence:** "just `use HasIcon` / `use HasId`" usually does **not** work for
those plain classes — a straight delegation would drag in Component coupling.
This is why most run-2 "consolidate-into-existing" claims against plain classes
were refuted. Where a plain surface genuinely shares a concern, the fix is a
**lightweight static-only companion trait**, not delegation to the Component-coupled
Foundation trait.

## `HasLabel` / `HasId` / `HasName` — already done, adoption map

These already exist in `Foundation/Concerns/` and are already the canonical owners.
`Component` composes them, so **every Field and every Entry inherits them for free**:

```
Component (uses HasId, HasLabel, HasName, HasDefault, HasVisibility, HasSize, ...)
 ├─ Field           (forms)      ✓ inherits
 └─ ViewComponent
     └─ Entry       (infolists)  ✓ inherits (adds HasColor/HasIcon/HasPlaceholder/HasTooltip locally)
```

There is nothing to *create*. The only gaps are three stragglers that bypass the base:

| Class | Bypasses | Fix | Notes |
|---|---|---|---|
| `Core/Components/DataComponent` (base of `Column`) | does **not** extend `Component`; hand-rolls label | delegate label/name to `HasLabel`/`HasName` | CONFIRMED (run 1). `Column` then stops re-overriding to reclaim `HasLabel` behaviour. Verify `getStatePath()`/`evaluate()` availability first. |
| `Filter` (standalone `Htmlable`) | hand-rolls name/label/default | `use HasName`, `HasLabel`, `HasDefault`, `HasDebounce` | CONFIRMED (run 1). **Not** `HasId` — Filter has no state path; id was never flagged. |
| `Modals/Concerns/HasModalProperties` | hand-rolls `id()`/`getId()` | **do not** delegate to `HasId` | `HasId::getId()` needs `evaluate()` + `getStatePath()`; Modal returns `?string`. Component-coupled → not a drop-in. Leave as-is or make a static-only id trait if ever consolidated. |

---

## TIER A — clean, low risk, do it (strictly confirmed)

### Forms (`packages/forms/src/Concerns/`) — byte-identical, no closures/DI
- `HasItemLimits` — `minItems/maxItems` in Select, Tags, Repeater (optionally align FileUpload `minFiles/maxFiles`).
- `HasCharacterLimits` — `minLength/maxLength` in TextInput, Textarea (`maxLength` also in the 4 editors). **Do NOT fold in `minValue/maxValue` — those wrap Closure+evaluate().**
- `CanBeSearchable` — `searchable`+`searchPrompt` in Select, CheckboxList (shared `trans('wire-forms::fields.search')`).
- `HasRelationship` — `relationship`+`titleAttribute` in Select, Tags (Repeater/MorphToSelect correctly excluded).
- `CanBeMultiple` — `multiple()` in Select, FileUpload.

### Filters
- `Filter` → delegate `HasName`, `HasLabel`, `HasDefault`, `HasDebounce` (TextFilter).
- Base `Filter::render()` + overridable `formFieldView(): string` hook → deletes the **byte-identical dead `render()` overrides** in DateFilter/NumberRangeFilter.
- `HasRangeLabels` (from/to) in DateFilter, NumberRangeFilter.

### Table columns (`packages/table/src/Concerns/`)
- `InteractsWithRecordDisabledState` — 3 editable columns.
- `EvaluatesRecordClosures` — ButtonColumn, PollColumn (thin resolver, mirrors `EvaluatesClosures`).
- `RendersBadgeSurface` — BadgeColumn, PollColumn.
- `HasRecordVersion` — ToggleColumn, TextInputColumn (optimistic-lock version).
- `resolveColorClass` wrapper → `HasColor::getTextColorClasses()` (BooleanColumn:81, IconColumn:110).
- Avatar size maps → new resolver `HasSize::getAvatarSizeClasses()` (ImageColumn, StackedColumn).

### Core
- `HasContent` — Display components + Callout (`$content`/`content()`/`getContent()`).
- **Delete `Actions/Concerns/HasButtonStyles`** — dead duplicate of `HasDynamicProperties` + `BaseAction` button assembly (medium severity).
- `HasBadge` — HeaderAction, ActionGroup. **Place in `Actions\Concerns`** (renders an actions-namespaced partial), not Foundation.
- `HasModalIcon` — icon+iconColor+color across all 4 modal config classes (medium).
- `Icon::coerce()` / `Color::coerce()` static helpers — collapse the scattered `instanceof ? ->value()` branch to one place (also fixes Stat/ChartItem icon normalization).

---

## TIER B — real duplication, but adjust target/scope (run-2 corrections)

- **`InteractsWithColor` (color STATE)** — highest reach; `HasColor` owns only the class
  resolvers, not the `$color` property + `color()` setter + enum normalization, which is
  re-encoded across Column, Radio, Toggle, Alert, Callout, Stat, ChartItem, actions.
  **Caveat:** `getColor()` cannot be one uniform method — return type and default fallback
  differ per surface → extract with an **overridable default**. Radio/Toggle coercion
  confirmed; **Rating is NOT in scope** (plain string assignment, no `Color` union).
- `CanBeCopyable` — ColorEntry, TextEntry, Column. **Trait must accept `?string $message`**;
  Column's default message uses a table-specific `Trans` key, which a core trait must not hardcode.
- `getStateAttribute()` — narrow to the two truly duplicated sites (CanBeSummarized:287,
  SummaryBatch:61), not "four copies".
- `getRawState()` for export — kind is **extract-new-then-delegate**; Column has no raw
  resolver yet (`getState` applies formatStateUsing+default).
- `InteractsWithBooleanState` — **only 2 classes** (IconEntry, IconColumn), 5 props + 2
  byte-identical override hooks. Not the 4 classes originally claimed.
- ImageEntry URL → `StoredFileUrlResolver` — real gap is private/signed disks, not data: URIs.
- Image config knobs (disk/circular/stacked/defaultUrl) — a thin shared trait is fine, but
  `$imageSize` differs fundamentally (px vs class) → keep local.

---

## REFUTED — do not do (adversarially killed)

| Claim | Why not |
|---|---|
| Merge the two `HasVisibility` (Actions vs Foundation) | Divergent semantics: Actions has inversion + single callback + context; Foundation has two separate `bool\|Closure`, no inversion. |
| Delegate Actions/Modal icon setters to Foundation `HasIcon` | Foundation `HasIcon` is 2-param closure/`evaluate()`; plain classes want static setters → needs a NEW lightweight static-icon trait, not delegation. |
| `HasOptions` Foundation consolidation | Normalization already centralized in `EnumResolver::normalizeOptions()`; `getOptions()` diverges (forms uses `evaluate()`/DI). |
| font-weight state (TextEntry/Column) | Class map already centralized in `HasFontWeight`; only a 1-line ternary duplicated — marginal. |
| `getStateType()` marker traits | Would invent empty marker traits — anti-pattern, not consolidation. |
| Rating color → `HasColor` | Rating needs a -500 bright scale + amber-400 default; `getTextColorClasses` is -600/-400. |
| heading+description (Widget/HasModalProperties) | Wrong pairing; the real shared model is the 4 Schema classes (EmptyState/Callout/Section/Step) via `string\|Closure`. |
| render-memo `HasView` vs `HasViewRenderCache` | Intentional split (instance vs static lifetime) — no change. |
| `HasRecordTriggers` renderInRowActions | Single-use, correct as-is. |
| Column prefix/suffix → `HasPrefixAndSuffix` | Name collision — on Column it means value-concat, not UI affix → rename to `prependText`/`appendText`. |
| MorphToSelect Type label; Filter placeholder; Filter visibility | Superficial / divergent semantics (Trans fallback, ReflectionFunction guard). |

---

## Recommended sequence

1. **TIER A forms count/flag traits** (`HasItemLimits`, `HasCharacterLimits`, `CanBeSearchable`, `HasRelationship`, `CanBeMultiple`) — cleanest, zero risk.
2. **`Filter` delegation + base `render()`** — confirmed by both passes, removes dead code. Also closes the `HasLabel`/`HasName` straggler.
3. **`InteractsWithColor`** (TIER B #1) — biggest reach, with an overridable default.
4. **Delete `HasButtonStyles`** + add `HasModalIcon` + `HasBadge`.
5. **`DataComponent`/`Column` → `HasLabel`/`HasName`** straggler (verify `getStatePath()`/`evaluate()` availability first).
6. Remaining table column traits + TIER B with the run-2 corrections applied.

Do the work on a dedicated branch — the `1.13.0` working tree has WIP RecordAction changes.

## Verification stats

- Finders: 11 (all completed). Verifiers: 62 across 2 runs. Subagent tokens ~3.16M, ~24 min wall-clock.
- Run 1: 33 verdicts, 4 refuted. Run 2: 29 verdicts, 0 errored, 16 confirmed, 13 refuted/rescoped.

---

## Implementation status (branch 1.13.1, 2026-07-24)

TIER A implemented across four commits; every group green on its package suite
plus `composer analyse` and `pint`, and the full suite (`composer test`) passes
(4485 passed, 2 skipped). Public component/action/modal APIs are unchanged — the
refactor only relocates duplicated members into shared concerns. New-trait lines
are 100% covered (dedicated unit tests where existing coverage did not already
exercise them); the only uncovered lines in touched files pre-date this work and
lie outside the diff.

**Done — 12 new traits + one render seam:**

- Forms (`packages/forms/src/Concerns/`): `HasItemLimits`, `HasCharacterLimits`,
  `CanBeSearchable`, `HasRelationship`, `CanBeMultiple`.
- Filters: base `Filter::render()` + overridable `filterView()` seam (deletes the
  dead Date/NumberRange overrides; Select/Ternary override only the view string);
  `TextFilter` delegates debounce to Foundation `HasDebounce`.
- Table columns (`packages/table/src/Concerns/`): `InteractsWithRecordDisabledState`,
  `EvaluatesRecordClosures`, `RendersBadgeSurface`, `HasRecordVersion`;
  Boolean/Icon columns drop their `resolveColorClass` wrapper.
- Core: `Actions\Concerns\HasBadge` (HeaderAction, ActionGroup),
  `Foundation\Concerns\HasContent` (Display base + Callout),
  `Modals\Concerns\HasModalIcon` (Modal, SlideOver, Wizard).

**Deviations — audit over-reach caught while reading the code (NOT implemented):**

- **Filter → HasName/HasLabel/HasDefault** — Filter has no `evaluate()`, which
  `HasLabel::getLabel()` / `HasDefault::getDefault()` require; signatures also
  diverge (`getLabel(): string` vs `?string`). Only the safe `HasDebounce` was taken.
- **HasRangeLabels** — DateFilter (`fromLabel/toLabel`) and NumberRangeFilter
  (`minLabel/maxLabel`) expose different public method names; a shared trait would
  add foreign methods.
- **Avatar-size resolver** — ImageColumn and StackedColumn use genuinely different
  size scales (`md` = w-10 vs w-9, `2xl` = w-16 vs w-20); centralising would change
  rendered output.
- **Delete HasButtonStyles** — it is the canonical target of a maintained BC
  deprecation shim (`Legacy…`/`DeprecatedAliases` tests), not dead code.
- **HasModalIcon** — applied to 3 of 4 modals; ConfirmationDialog keeps its own
  variant (danger default + null-preserving `icon()`).
- **Icon/Color::coerce()** — deferred: replacing the `instanceof ? ->value()`
  branch is a codebase-wide sweep, not a localised extraction (run 2 confirmed only
  the narrow Stat/ChartItem case).

## TIER B implementation status (branch 1.13.1, 2026-07-24)

TIER B completed on the same terms — every touched group green on its package
suite plus `composer analyse` and `pint`, public APIs unchanged, new-trait lines
covered. The colour-resolver half of the "two `HasColor`" item was already done
before this pass (`Actions\Concerns\HasColor` and the deprecated `Concerns\HasColor`
are thin BC aliases of the Foundation resolver); what remained was the colour
**state**.

**Done:**

- **`InteractsWithColor` (colour STATE)** — the `$color` slot + enum-normalising
  `color()` + nullable `getColor()`, split from the resolver-only `HasColor`.
  Adopted on ChartItem, ActionHalt, ModalFooterAction, ConfirmationDialog (the
  latter two override `getColor()` for a non-null default), then on **Stat**
  (full delegation) and **ActionGroup** (slot + setter, keeps its `getColor(): string`
  Gray fallback — the trait's documented override case).
- **`CanBeCopyable`** (ColorEntry/TextEntry; Column keeps its message-aware variant)
  and **`HasImageConfig`** (disk/circular/stacked/defaultImageUrl on ImageEntry +
  ImageColumn).
- **`InteractsWithBooleanState`** (`Foundation\Concerns\`) — the `boolean()` display
  mode (flag + four true/false defaults + the truthiness rule) shared by IconEntry
  and IconColumn; BooleanEntry inherits it via IconEntry. The `resolveState*Override`
  hooks stay one-line delegations per class (the state traits already define them as
  no-op defaults, so a same-named trait method would collide).
- **`CanBeSummarized::getSummaryColumnName()`** — one owner for the summarized-column
  rule (aggregate attribute ↦ name fallback) that `SummaryBatch` and `getSummaryTarget`
  each encoded inline.
- **`Column::getRawState()`** — the unformatted twin of `getState()`; `ResolvesExportValue`
  delegates to it instead of reaching into the column's aggregate internals, and only
  applies the export display step (`EnumResolver::display`) on top. Behaviour
  byte-identical to the export's previous raw walk.
- **`DataComponent` → Foundation `HasName`** — the straggler base class stops
  hand-rolling `$name`/`getName()`.

**Deviations — over-reach or genuine divergence caught while reading the code:**

- **`InteractsWithColor` on Column** — its `color()` carries surface-specific
  fluent-API guidance (point per-row colouring at `BadgeColumn::colorUsing()`) that
  reflection surfaces to agents; delegating the setter would drop it, and delegating
  only the slot/getter leaves the setter re-encoded anyway for ~4 lines in a hot file.
- **`InteractsWithColor` on Entry/Button/`HasDynamicProperties`** — colour is
  closure-evaluated there (`getColor(mixed $context): string`), which the plain
  state trait cannot model.
- **`DataComponent`/Column → `HasLabel`** — both auto-generate labels from the
  relation-path *column* name; Foundation `HasLabel` headlines the full dotted
  `getName()` and returns `?string`. Delegating would change rendered headers and
  break relation-column labels. Only `HasName` was taken.
- **`InteractsWithBooleanState` on BooleanColumn** — it has no `boolean` flag or
  override hooks and carries its own true/false *labels*; a different shape.
- **ImageEntry URL → `StoredFileUrlResolver`** — `ImageColumn` already delegates,
  but ImageEntry has divergent passthrough (root-relative `/…` and protocol-relative
  `//…` pass through; a diskless path returns raw) and no signed-URL state. Routing
  it through the resolver would regress those cases and needs new `visibility`/`expiry`
  state — a feature, not a consolidation. Left local, matching the `HasImageConfig`
  commit's note that URL resolution "genuinely differs".

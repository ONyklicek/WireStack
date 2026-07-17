# ADR 0018: State Machine / Workflow Seam

## Status

PROPOSED

Design-only ADR (decision: "ADR now, implementation in V2"). Drafted as pre-V2
item **N5** (`architecture/plans/ddd-enterprise-roadmap.md`); implementation is
**V2.4** (`architecture/plans/v2-master-plan.md`). Closes v1-gaps #3 (no
first-class workflow/process layer).

## Context

Wire has actions, modals, and status rendering, but **no built-in workflow
model**: no transition model, no state-machine / process policy, no approval-flow
owner. Process-heavy ERP/CRM use-cases (order lifecycle, MES routing, CRM
pipeline, approval chains) are today ad-hoc application code.

Relevant canonical owners **already exist** and this ADR must build on them, not
duplicate (CLAUDE.md architectural invariants):

- **Enum semantics:** `Foundation/Contracts/Enum/{HasColor,HasLabel,HasIcon}` +
  `Foundation/Support/EnumResolver` — the canonical way an enum carries color,
  label, and icon (enum casts auto-resolve badge color/icon; ADR-era enum work).
- **Status rendering:** `packages/table/src/Columns/BadgeColumn.php` already
  renders colored/iconed badges and understands enum casts.
- **Execution:** `Core/Actions/ActionPipeline`, `BaseAction`, the audit event
  system (`Audit/Events/*`), and opt-in optimistic locking
  (`Form::optimisticLock`, N2) already provide policy-checked, audited, safe
  mutation.

**Naming hazard (verified):** `Foundation/Concerns/HasState` **already exists**
and means *form field value state* (`HasStateAccessors`), and `Core/State/*`
(`StateContainer`, `StateHydrator`) is component state. The N5 sketch's proposed
"canonical `HasState`" name would **collide** with these. This ADR therefore uses
**status / workflow** vocabulary (`HasStatus`, `WorkflowState`, `TransitionAction`),
not `HasState`.

## Decision

Provide a **workflow seam**, not a business workflow engine. The package owns the
*shape* of a state machine (states, allowed transitions, guards, side-effect
hooks) and delegates the *meaning* to the domain. Three canonical pieces:

### 1. Status model — enum-backed, on existing enum contracts

A domain status is expressed as a PHP enum implementing the existing
`Enum\HasColor` / `Enum\HasLabel` / `Enum\HasIcon` contracts (already resolved by
`EnumResolver`). The transition rules live on a `WorkflowState` definition (or on
the enum via an optional contract), so color/label/icon reuse the canonical
owner — **no new color/label/icon map.**

```php
enum OrderStatus: string implements HasColor, HasLabel, HasIcon
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    // color()/label()/icon() resolved via existing Enum contracts + EnumResolver
}
```

### 2. Transition definition + guard

A declarative allowed-transition map with per-transition **guards** (a closure /
policy that may veto) and optional side-effect hooks. It is headless (no Blade,
ADR 0017 invariant B/C) and delegates persistence/audit to existing execution:

```php
WorkflowState::for(OrderStatus::class)
    ->column('status')                                  // model attribute
    ->allow(OrderStatus::Draft, OrderStatus::Confirmed)
    ->allow(OrderStatus::Confirmed, OrderStatus::Shipped)
    ->allow([OrderStatus::Draft, OrderStatus::Confirmed], OrderStatus::Cancelled)
    ->guard(OrderStatus::Confirmed, fn ($record, $user) => $record->hasLines())
    ->after(OrderStatus::Shipped, fn ($record) => /* dispatch shipment */ );
```

- `canTransition($record, $to, $user): bool` — allowed-set ∩ guard ∩ policy.
- Illegal transition → clear `IllegalTransitionException`; guard veto → domain
  notification, no mutation.

### 3. `StatusColumn` + `TransitionAction` — UI adapters

- **`StatusColumn`** specializes `BadgeColumn` (invariant D: specialized column
  type, small base). It reads the status enum and renders via the existing badge
  path — enum color/icon come for free.
- **`TransitionAction`** extends `BaseAction`: offered only for transitions the
  guard/policy currently allows (delegates to `HasAuthorization` +
  `WorkflowState::canTransition`), runs through `ActionPipeline`, writes an audit
  entry, and (opt-in) respects optimistic locking. Bulk variant follows the
  existing `BulkAction` path.

### Delegation boundary (what the package does NOT own)

- No business process definitions, no BPMN, no approval-org modeling.
- No persistence engine — transitions mutate through existing form/action save.
- No scheduler — long-running/async steps use V2.4 queued operations.

The package supplies the **safe transition primitive**; the domain composes
processes from it.

## Relationship to other layers

- **V2.0 `DataSource`:** status can be projected in read models; a read-only
  status view works over any source. `StatusColumn` consumes `RecordContract`.
- **V2.3 owners:** a `Resource` declares its `WorkflowState` once; list/edit/view
  and relation managers reuse it (single-declaration consistency).
- **Audit (done):** every transition emits an existing `Audit` event — the
  who/what/when trail is free.
- **Tenancy (V2.4):** transitions run through the same scoped execution, so tenant
  scope holds automatically.

## Consequences

### Positive
- First-class, guarded, audited state transitions instead of ad-hoc `->update`.
- Reuses canonical enum color/label/icon + BadgeColumn + ActionPipeline + audit —
  minimal new surface, maximal reuse.
- Domain keeps ownership of process meaning; package guarantees legal, safe,
  policy-checked, audited transitions.

### Negative
- New concepts (`WorkflowState`, transitions, guards) to learn and document.
- Two "state" vocabularies now coexist (field value-state vs domain status) —
  docs must disambiguate to avoid the `HasState` naming confusion this ADR dodges.

### Risks
- **Scope creep into a workflow engine.** Must stay a transition seam; approval
  chains / process orchestration belong to the domain. Mitigation: explicit
  delegation boundary above.
- **Concurrent transitions** (two users advance the same record) → data race.
  Mitigation: transitions honor optimistic locking (N2).
- **Naming.** Any concern name must avoid `HasState`/`Core/State`. Mitigation:
  status/workflow vocabulary is normative in this ADR.

## Open questions

1. Do transition rules live on the enum (an optional `HasTransitions` contract)
   or only on a separate `WorkflowState` definition? (Enum-local = colocated;
   separate = testable without touching the enum, and works for non-enum string
   statuses.)
2. Guard signature: `($record, $user)` closure vs a `TransitionGuard` contract
   for reusable/DI-able guards? (Align with `HasAuthorization` callback style.)
3. Are side-effect hooks (`after()`) synchronous, or do they default to the V2.4
   queue for long steps?
4. Does `StatusColumn` also support non-enum string statuses (legacy schemas), or
   is enum-backed status required?

## References

- `architecture/plans/v2-master-plan.md` — V2.4 (implementation), N5 (this ADR as gate).
- `architecture/plans/ddd-enterprise-roadmap.md` — N5 origin.
- `architecture/plans/v1-gaps.md` — gap #3 (missing workflow layer).
- `architecture/decisions/0017-erp-crm-application-architecture.md` — invariants B/C/D/G.
- Canonical owners reused: `Foundation/Contracts/Enum/{HasColor,HasLabel,HasIcon}`,
  `Foundation/Support/EnumResolver`, `packages/table/src/Columns/BadgeColumn.php`,
  `Core/Actions/ActionPipeline`, `Audit/Events/*`, `Form::optimisticLock` (N2).
- Naming hazard: existing `Foundation/Concerns/HasState.php` (field value-state),
  `Core/State/*` (component state) — deliberately **not** reused for domain status.

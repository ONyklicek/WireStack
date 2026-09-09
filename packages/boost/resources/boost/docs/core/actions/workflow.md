---
order: 60
summary: "Which state moves are legal, where each guard lives, and how a refusal reaches the person who tried it."
---

# Workflow And Transitions

A record that moves through states usually ends up with the rules spread across
controllers and `->update()` calls, where nothing says which moves are legal.
This page is the one place that does — and the line between holding that shape
and becoming a workflow engine, which this deliberately is not.

## Workflow And Transitions

A record that moves through states — draft, confirmed, shipped — usually ends up
with the rules spread across controllers and `->update()` calls, where nothing
says which moves are legal. `WorkflowState` holds that shape in one place.

```php
use NyonCode\WireCore\Core\Workflow\WorkflowState;

$orders = WorkflowState::for(OrderStatus::class)
    ->column('status')                                              // [tl! focus:5]
    ->allow(OrderStatus::Draft, OrderStatus::Confirmed)
    ->allow(OrderStatus::Confirmed, OrderStatus::Shipped)
    ->allow([OrderStatus::Draft, OrderStatus::Confirmed], OrderStatus::Cancelled)
    ->guard(OrderStatus::Confirmed, fn ($order) => $order->lines()->exists())
    ->after(OrderStatus::Shipped, fn ($order) => ShipmentJob::dispatch($order));
```

`allow()` takes a list of origins because "anything up to here may be cancelled"
is the common shape, and writing it three times is how one of the three gets
forgotten.

**A seam, not a workflow engine.** It owns the shape and delegates the meaning:
no process definitions, no approval modelling, no scheduler. Transitions save
through the ordinary path, which is how tenancy scoping and audit come along
without being wired again.

There is no colour, label or icon here on purpose. The status is an enum
implementing `Enum\HasColor` / `HasLabel` / `HasIcon`, and `BadgeColumn` already
renders that — a second map would be a parallel vocabulary that drifts.

### Two refusals that behave differently

```php
$orders->transition($order, OrderStatus::Shipped);   // from draft → throws
$orders->transition($order, OrderStatus::Confirmed); // no lines → returns false
```

An **illegal** transition throws: the machine says that edge does not exist, and
staying silent leaves a record where the user believes it moved on. A **guard
veto** returns false, because "not yet" is a domain answer rather than a broken
machine — the caller reports it.

Guards on one state all have to pass. An approval limit and a completeness check
are separate rules, and `&&`-ing them into one closure loses which of them said
no.

`after()` hooks run once the move is persisted, so a hook that dispatches a
shipment cannot fire for a save that then rolls back.

### Offering it in the UI

```php
use NyonCode\WireCore\Actions\TransitionAction;

TransitionAction::to(OrderStatus::Confirmed)->workflow($orders)
```

An ordinary action in every other respect — same pipeline, same authorization,
can confirm, can queue. What it adds is two things, and both happen without the
surface drawing it knowing a workflow exists:

**It hides itself where the move would not work.** `isHidden($record)` — what
every action surface already asks before rendering, and `canExecute($record)`
before running — is true unless the edge exists **and** its guards pass for this
record and this user. An action offered for a transition the user cannot complete
is an action that exists to be refused, and a refusal they could not have
predicted reads as an application bug rather than a rule of the process.
`isAvailableFor($record, $user)` answers the machine's half alone, for a UI that
wants to ask directly.

**Pressing it performs the move.** Attaching a workflow sets the action's
callback to the transition, so a button needs no `->action()` of its own. An
explicit `->action()` still wins, in either order.

Its label, colour and icon come from the target enum through the same canonical
resolution `BadgeColumn` uses, so the button and the badge cannot disagree about
what "Confirmed" looks like. An explicit `->label()` still wins.

The action's own `->visible()` and the machine's answer stay separate questions —
both must hold, and neither quietly overrules the other. A check with no record
(a header action, a view asking bare) leaves the machine out of it.

### What a UI should offer

```php
$orders->availableFrom($order, auth()->user());   // only the moves that would work
```

## Related

- [Actions](index.md) — what a transition is usually triggered by
- [Foundation: Enums](../foundation/enums.md) — where a state's label, colour and icon come from
- [Audit Log](../audit.md) — the trail a transition leaves
- [BadgeColumn](../../table/columns/badge.md) — the column that renders the state

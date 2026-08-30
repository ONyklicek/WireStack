<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Workflow\WorkflowState;
use NyonCode\WireCore\Exceptions\IllegalTransitionException;

/*
 * The shape of a state machine, not a workflow engine.
 *
 * Two refusals that behave differently on purpose: an illegal edge throws,
 * because a record that quietly stayed put while the user believes it moved on
 * is worse than a stop; a guard veto answers false, because "not yet" is a
 * domain decision rather than a broken machine.
 */
enum WfStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}

enum WfOther: string
{
    case Elsewhere = 'elsewhere';
}

class WfOrder extends Model
{
    protected $table = 'wf_orders';

    protected $guarded = [];

    public $timestamps = false;
}

function wfMachine(): WorkflowState
{
    return WorkflowState::for(WfStatus::class)
        ->column('status')
        ->allow(WfStatus::Draft, WfStatus::Confirmed)
        ->allow(WfStatus::Confirmed, WfStatus::Shipped)
        // The common shape: anything up to here may be cancelled.
        ->allow([WfStatus::Draft, WfStatus::Confirmed], WfStatus::Cancelled);
}

beforeEach(function () {
    Schema::create('wf_orders', function (Blueprint $t) {
        $t->id();
        $t->string('status');
        $t->integer('lines')->default(0);
    });

    $this->order = WfOrder::create(['status' => 'draft', 'lines' => 0]);
});

afterEach(function () {
    Schema::dropIfExists('wf_orders');
});

// ─── The edges ───────────────────────────────────────────────────────────────

it('moves along an edge it was given', function () {
    expect(wfMachine()->transition($this->order, WfStatus::Confirmed))->toBeTrue()
        ->and($this->order->fresh()->status)->toBe('confirmed');
});

it('refuses an edge that does not exist, loudly', function () {
    // Draft → Shipped was never declared. Silence here means a record that
    // stayed in draft while the user believes it shipped.
    wfMachine()->transition($this->order, WfStatus::Shipped);
})->throws(IllegalTransitionException::class, 'does not allow');

it('refuses a state from another machine', function () {
    wfMachine()->transition($this->order, WfOther::Elsewhere);
})->throws(IllegalTransitionException::class, 'is not a case of');

it('says which column it reads, and refuses to guess', function () {
    WorkflowState::for(WfStatus::class)->isAllowed($this->order, WfStatus::Confirmed);
})->throws(IllegalTransitionException::class, 'does not say which column');

it('expands a list of origins into separate edges', function () {
    $machine = wfMachine();

    expect($machine->isAllowed($this->order, WfStatus::Cancelled))->toBeTrue();

    $this->order->update(['status' => 'confirmed']);

    expect($machine->isAllowed($this->order->fresh(), WfStatus::Cancelled))->toBeTrue();
});

// ─── Guards ──────────────────────────────────────────────────────────────────

it('answers false rather than throwing when a guard vetoes', function () {
    // "Not yet" is a domain answer, not a broken machine.
    $machine = wfMachine()->guard(WfStatus::Confirmed, fn (WfOrder $o): bool => $o->lines > 0);

    expect($machine->transition($this->order, WfStatus::Confirmed))->toBeFalse()
        ->and($this->order->fresh()->status)->toBe('draft');
});

it('requires every guard on a state to pass', function () {
    // An approval limit and a completeness check are separate rules; &&-ing them
    // into one closure loses which of them said no.
    $machine = wfMachine()
        ->guard(WfStatus::Confirmed, fn (WfOrder $o): bool => $o->lines > 0)
        ->guard(WfStatus::Confirmed, fn (WfOrder $o, $user): bool => $user === 'manager');

    $this->order->update(['lines' => 2]);

    expect($machine->canTransition($this->order->fresh(), WfStatus::Confirmed, 'clerk'))->toBeFalse()
        ->and($machine->canTransition($this->order->fresh(), WfStatus::Confirmed, 'manager'))->toBeTrue();
});

it('separates "the edge exists" from "the guard lets you"', function () {
    $machine = wfMachine()->guard(WfStatus::Confirmed, fn (): bool => false);

    expect($machine->isAllowed($this->order, WfStatus::Confirmed))->toBeTrue()
        ->and($machine->canTransition($this->order, WfStatus::Confirmed))->toBeFalse();
});

// ─── What a UI should offer ──────────────────────────────────────────────────

it('offers only the transitions that would actually succeed', function () {
    // An action for a transition the user cannot complete is an action that
    // exists to be refused.
    $machine = wfMachine()->guard(WfStatus::Confirmed, fn (WfOrder $o): bool => $o->lines > 0);

    expect($machine->availableFrom($this->order))->toBe([WfStatus::Cancelled]);

    $this->order->update(['lines' => 1]);

    expect($machine->availableFrom($this->order->fresh()))
        ->toBe([WfStatus::Confirmed, WfStatus::Cancelled]);
});

it('offers nothing from a terminal state', function () {
    $this->order->update(['status' => 'shipped']);

    expect(wfMachine()->availableFrom($this->order->fresh()))->toBe([]);
});

// ─── After hooks ─────────────────────────────────────────────────────────────

it('runs after-hooks once the move is persisted', function () {
    // After, not during: a hook that dispatches a shipment must not fire for a
    // save that then rolls back.
    $seen = [];

    $machine = wfMachine()->after(WfStatus::Confirmed, function (WfOrder $o) use (&$seen): void {
        $seen[] = $o->fresh()->status;
    });

    $machine->transition($this->order, WfStatus::Confirmed);

    expect($seen)->toBe(['confirmed']);
});

it('runs no after-hook for a transition a guard stopped', function () {
    $ran = false;

    $machine = wfMachine()
        ->guard(WfStatus::Confirmed, fn (): bool => false)
        ->after(WfStatus::Confirmed, function () use (&$ran): void {
            $ran = true;
        });

    $machine->transition($this->order, WfStatus::Confirmed);

    expect($ran)->toBeFalse();
});

it('reads a status the model casts to an enum', function () {
    // The status is an enum on the way in and a scalar in the column; the
    // machine reads through the canonical EnumResolver rather than assuming one.
    $this->order->update(['status' => WfStatus::Confirmed->value]);

    expect(wfMachine()->isAllowed($this->order->fresh(), WfStatus::Shipped))->toBeTrue();
});

it('refuses to offer a state declared from another machine', function () {
    // allow() takes any enum, so a machine can be mis-declared. Caught when the
    // transitions are read rather than silently offering a state that does not
    // exist in this workflow.
    $machine = wfMachine()->allow(WfStatus::Draft, WfOther::Elsewhere);

    $machine->availableFrom($this->order);
})->throws(IllegalTransitionException::class, 'is not a case of');

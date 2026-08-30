<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Actions\TransitionAction;
use NyonCode\WireCore\Core\Workflow\WorkflowState;
use NyonCode\WireCore\Exceptions\IllegalTransitionException;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

/*
 * The action that moves a record one step.
 *
 * The property worth pinning is the absence: an action offered for a transition
 * the user cannot complete is an action that exists to be refused, and a refusal
 * they could not have predicted reads as an application bug rather than a rule
 * of the process.
 */
enum TaStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirm order',
            self::Shipped => 'Mark shipped',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Confirmed => 'success',
            self::Shipped => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return 'outline:check';
    }
}

class TaOrder extends Model
{
    protected $table = 'ta_orders';

    protected $guarded = [];

    public $timestamps = false;
}

function taWorkflow(): WorkflowState
{
    return WorkflowState::for(TaStatus::class)
        ->column('status')
        ->allow(TaStatus::Draft, TaStatus::Confirmed)
        ->allow(TaStatus::Confirmed, TaStatus::Shipped)
        ->guard(TaStatus::Confirmed, fn (TaOrder $o): bool => $o->lines > 0);
}

beforeEach(function () {
    Schema::create('ta_orders', function (Blueprint $t) {
        $t->id();
        $t->string('status');
        $t->integer('lines')->default(0);
    });

    $this->order = TaOrder::create(['status' => 'draft', 'lines' => 0]);
});

afterEach(function () {
    Schema::dropIfExists('ta_orders');
});

// ─── Presentation comes from the enum ────────────────────────────────────────

it('takes its label, colour and icon from the target state', function () {
    // The same canonical resolution BadgeColumn renders the status with, so the
    // button and the badge cannot disagree about what "Confirmed" looks like.
    $action = TransitionAction::to(TaStatus::Confirmed);

    expect($action->getLabel())->toBe('Confirm order')
        ->and($action->getColor())->toBe('success')
        ->and($action->getIcon())->toBe('outline:check');
});

it('lets an explicit label win, as on any action', function () {
    expect(TransitionAction::to(TaStatus::Confirmed)->label('Approve')->getLabel())->toBe('Approve');
});

// ─── Offered only when it would work ─────────────────────────────────────────

it('is not offered while a guard would veto it', function () {
    $action = TransitionAction::to(TaStatus::Confirmed)->workflow(taWorkflow());

    expect($action->isAvailableFor($this->order))->toBeFalse();

    $this->order->update(['lines' => 1]);

    expect($action->isAvailableFor($this->order->fresh()))->toBeTrue();
});

it('is not offered for an edge that does not exist from here', function () {
    $action = TransitionAction::to(TaStatus::Shipped)->workflow(taWorkflow());

    expect($action->isAvailableFor($this->order))->toBeFalse();
});

it('keeps its own visible() separate from the machine-s answer', function () {
    // Two different questions: one the developer wrote, one the process decides.
    // Neither should quietly overrule the other.
    $this->order->update(['lines' => 1]);

    $action = TransitionAction::to(TaStatus::Confirmed)->workflow(taWorkflow())->visible(false);

    expect($action->isAvailableFor($this->order->fresh()))->toBeTrue()
        ->and($action->isHidden())->toBeTrue();
});

it('is an ordinary action when no workflow is attached', function () {
    expect(TransitionAction::to(TaStatus::Confirmed)->isAvailableFor($this->order))->toBeTrue();
});

// ─── Performing it ───────────────────────────────────────────────────────────

it('moves the record', function () {
    $this->order->update(['lines' => 1]);

    $action = TransitionAction::to(TaStatus::Confirmed)->workflow(taWorkflow());

    expect($action->transition($this->order->fresh()))->toBeTrue()
        ->and($this->order->fresh()->status)->toBe('confirmed');
});

it('answers false when a guard vetoes, and throws on an illegal edge', function () {
    $vetoed = TransitionAction::to(TaStatus::Confirmed)->workflow(taWorkflow());
    expect($vetoed->transition($this->order))->toBeFalse();

    $illegal = TransitionAction::to(TaStatus::Shipped)->workflow(taWorkflow());
    expect(fn () => $illegal->transition($this->order))->toThrow(IllegalTransitionException::class);
});

it('does nothing when it has no machine to ask', function () {
    expect(TransitionAction::to(TaStatus::Confirmed)->transition($this->order))->toBeFalse()
        ->and($this->order->fresh()->status)->toBe('draft');
});

it('carries the state it targets', function () {
    expect(TransitionAction::to(TaStatus::Shipped)->getTarget())->toBe(TaStatus::Shipped);
});

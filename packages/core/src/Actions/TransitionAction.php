<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Workflow\WorkflowState;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use UnitEnum;

/**
 * Moves a record to one state, and offers itself only when that would work.
 *
 * An ordinary {@see Action} in every other respect — it runs through the same
 * pipeline, is authorized the same way, can confirm, can queue — so nothing
 * about actions has to learn what a workflow is. What it adds is the visibility
 * rule: the button is absent unless the edge exists *and* its guards pass for
 * this record and this user.
 *
 *   TransitionAction::make(OrderStatus::Confirmed)
 *       ->workflow($orderWorkflow)
 *
 * That absence is the point. An action offered for a transition the user cannot
 * complete is an action that exists to be refused, and a refusal the user could
 * not have predicted reads as a bug in the application rather than a rule of the
 * process.
 *
 * Label, colour and icon come from the target enum through the canonical
 * `Enum\HasLabel` / `HasColor` / `HasIcon` resolution — the same path
 * `BadgeColumn` renders the status with, so the button and the badge cannot
 * disagree about what "Confirmed" looks like.
 */
class TransitionAction extends Action
{
    protected ?WorkflowState $workflow = null;

    protected ?UnitEnum $target = null;

    /**
     * @param  UnitEnum  $target  The state this action moves a record to.
     */
    public static function to(UnitEnum $target): static
    {
        $value = EnumResolver::scalar($target);

        $action = static::make('transition-'.(string) $value);
        $action->target = $target;

        // The enum's own label, so the button and the badge agree. An explicit
        // ->label() still wins, as it does on any action.
        $label = EnumResolver::label($target);
        $action->label(is_string($label) ? $label : (string) $value);

        $colour = EnumResolver::color($target);
        if ($colour !== null) {
            $action->color(is_string($colour) ? $colour : $colour->value);
        }

        $icon = EnumResolver::icon($target);
        if ($icon !== null) {
            $action->icon($icon);
        }

        return $action;
    }

    /**
     * The machine that decides whether this transition is on offer — and, unless
     * the caller said otherwise, what pressing the button does.
     *
     * The default callback is the other half of the defect V2.6 step 4 found: an
     * action runs whatever `getActionCallback()` returns, `TransitionAction` set
     * none, so a transition button rendered, was clicked, and did nothing at all
     * — quieter than an error and harder to notice. Attaching a machine is the
     * moment the intent is unambiguous, so that is where the default is set.
     *
     * An explicit `->action()` still wins whichever order the two are called in:
     * one written before this leaves the callback set, and one written after
     * replaces it, which is how every other action behaves.
     */
    public function workflow(WorkflowState $workflow): static
    {
        $this->workflow = $workflow;

        if ($this->actionCallback === null) {
            $this->action(function (mixed $record = null): void {
                if ($record instanceof Model) {
                    $this->transition($record);
                }
            });
        }

        return $this;
    }

    public function getTarget(): ?UnitEnum
    {
        return $this->target;
    }

    /**
     * Whether to offer this transition for a record.
     *
     * Separate from the action's own `->visible()` because they answer different
     * questions: that one is the developer's condition, this is the machine's.
     * Both must hold, which is what {@see isHidden()} composes below — keeping
     * them apart means a `->visible()` a developer wrote is never quietly
     * overruled by the workflow, nor the other way round.
     */
    public function isAvailableFor(Model $record, mixed $user = null): bool
    {
        if ($this->workflow === null || $this->target === null) {
            // No machine attached: an ordinary action, offered as one.
            return true;
        }

        return $this->workflow->canTransition($record, $this->target, $user);
    }

    /**
     * The machine's answer, joined to the action's own.
     *
     * This override is what makes the absence above real. Until V2.6 step 4
     * nothing called `isAvailableFor()` outside its own tests: every surface
     * that draws an action asks `isHidden($record)` — `actions/button.blade.php`
     * before rendering, `canExecute($record)` before running — and neither knew
     * about a workflow. So a table offered every transition on every row, and a
     * user who clicked one the machine forbids got an exception where the docs
     * promised a button that was simply not there. Found by putting a real
     * workflow on a real table in the workbench; no unit test could see it,
     * because the method under test was the one nothing called.
     *
     * A record-less context (a header action, a view asking bare) leaves the
     * machine out of it: there is no record to ask about, and hiding on that
     * basis would remove buttons that have nothing to do with a workflow.
     */
    public function isHidden(mixed $context = null): bool
    {
        if (parent::isHidden($context)) {
            return true;
        }

        return $context instanceof Model && ! $this->isAvailableFor($context);
    }

    /**
     * Perform the move.
     *
     * @return bool False when a guard vetoed — the caller reports that; an
     *              illegal edge throws from {@see WorkflowState::transition()}.
     */
    public function transition(Model $record, mixed $user = null): bool
    {
        if ($this->workflow === null || $this->target === null) {
            return false;
        }

        return $this->workflow->transition($record, $this->target, $user);
    }
}

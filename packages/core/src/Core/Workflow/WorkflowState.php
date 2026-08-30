<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Workflow;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Exceptions\IllegalTransitionException;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use UnitEnum;

/**
 * Which state may follow which, and what has to be true first.
 *
 * A **seam, not a workflow engine** (ADR 0018). It owns the shape — the states,
 * the allowed edges, the guards, the side effects — and delegates every meaning
 * to the domain: no process definitions, no approval modelling, no scheduler,
 * and no persistence of its own. Transitions mutate through the same save path
 * everything else uses, which is how tenancy and audit come along for free.
 *
 *   WorkflowState::for(OrderStatus::class)
 *       ->column('status')
 *       ->allow(OrderStatus::Draft, OrderStatus::Confirmed)
 *       ->allow([OrderStatus::Draft, OrderStatus::Confirmed], OrderStatus::Cancelled)
 *       ->guard(OrderStatus::Confirmed, fn ($record) => $record->lines()->exists())
 *       ->after(OrderStatus::Shipped, fn ($record) => ShipmentJob::dispatch($record));
 *
 * Colour, label and icon are deliberately absent: the status is an enum
 * implementing the canonical `Enum\HasColor` / `HasLabel` / `HasIcon`, and
 * `BadgeColumn` already renders that. A second map here would be the parallel
 * vocabulary this codebase keeps deleting.
 *
 * The two refusals differ on purpose. An **illegal** transition throws — the
 * machine says that edge does not exist, and continuing would leave a record
 * where the user thinks it moved on. A **guard veto** does not throw: it is the
 * domain saying "not yet", which is an answer, and the caller reports it.
 */
final class WorkflowState
{
    /** @var array<string, array<int, string>> from-value => allowed to-values */
    private array $transitions = [];

    /** @var array<string, array<int, Closure>> to-value => guards */
    private array $guards = [];

    /** @var array<string, array<int, Closure>> to-value => after hooks */
    private array $afterHooks = [];

    private ?string $column = null;

    /**
     * @param  class-string  $status  The backed enum the workflow is defined over.
     */
    private function __construct(public readonly string $status) {}

    /**
     * @param  class-string  $status
     */
    public static function for(string $status): self
    {
        return new self($status);
    }

    /** The model attribute holding the current state. */
    public function column(string $column): self
    {
        $this->column = $column;

        return $this;
    }

    public function getColumn(): string
    {
        if ($this->column === null) {
            throw IllegalTransitionException::missingColumn($this->status);
        }

        return $this->column;
    }

    /**
     * Declare an edge. `$from` takes a list, because "anything up to here may be
     * cancelled" is the common shape and writing it out three times is how one
     * of the three gets forgotten.
     *
     * @param  UnitEnum|array<int, UnitEnum>  $from
     */
    public function allow(UnitEnum|array $from, UnitEnum $to): self
    {
        $toValue = $this->valueOf($to);

        foreach (is_array($from) ? $from : [$from] as $state) {
            $this->transitions[$this->valueOf($state)][] = $toValue;
        }

        return $this;
    }

    /**
     * A condition that must hold before entering `$to`.
     *
     * Receives the record and the acting user. Several guards on one state all
     * have to pass — an approval limit and a completeness check are separate
     * rules, and `&&`-ing them into one closure loses which of them said no.
     */
    public function guard(UnitEnum $to, Closure $guard): self
    {
        $this->guards[$this->valueOf($to)][] = $guard;

        return $this;
    }

    /**
     * Run after a transition has been persisted.
     *
     * After, not during: a hook that dispatches a shipment must not fire for a
     * save that then rolls back. Long work belongs on a queue — the seam owns no
     * scheduler (ADR 0018).
     */
    public function after(UnitEnum $to, Closure $hook): self
    {
        $this->afterHooks[$this->valueOf($to)][] = $hook;

        return $this;
    }

    /**
     * The states this record may move to right now — allowed edges that also
     * pass their guards.
     *
     * What a UI should offer. An action for a transition the user cannot
     * complete is an action that exists to be refused.
     *
     * @return array<int, UnitEnum>
     */
    public function availableFrom(Model $record, mixed $user = null): array
    {
        $current = $this->currentValue($record);
        $available = [];

        foreach ($this->transitions[$current] ?? [] as $to) {
            $case = $this->caseOf($to);

            if ($this->guardsPass($case, $record, $user)) {
                $available[] = $case;
            }
        }

        return $available;
    }

    /** Whether the edge exists at all, guards ignored. */
    public function isAllowed(Model $record, UnitEnum $to): bool
    {
        return in_array($this->valueOf($to), $this->transitions[$this->currentValue($record)] ?? [], true);
    }

    /** Whether the edge exists *and* its guards pass. */
    public function canTransition(Model $record, UnitEnum $to, mixed $user = null): bool
    {
        return $this->isAllowed($record, $to) && $this->guardsPass($to, $record, $user);
    }

    /**
     * Move the record, or say why not.
     *
     * @return bool False when a guard vetoed — an answer, not a failure.
     *
     * @throws IllegalTransitionException When the edge does not exist.
     */
    public function transition(Model $record, UnitEnum $to, mixed $user = null): bool
    {
        if (! $to instanceof $this->status) {
            throw IllegalTransitionException::notAStatus($this->valueOf($to), $this->status);
        }

        if (! $this->isAllowed($record, $to)) {
            throw IllegalTransitionException::notAllowed(
                (string) $this->currentValue($record),
                (string) $this->valueOf($to),
                $this->status,
            );
        }

        if (! $this->guardsPass($to, $record, $user)) {
            return false;
        }

        $record->setAttribute($this->getColumn(), $this->valueOf($to));
        $record->save();

        foreach ($this->afterHooks[$this->valueOf($to)] ?? [] as $hook) {
            $hook($record, $user);
        }

        return true;
    }

    private function guardsPass(UnitEnum $to, Model $record, mixed $user): bool
    {
        foreach ($this->guards[$this->valueOf($to)] ?? [] as $guard) {
            if (! $guard($record, $user)) {
                return false;
            }
        }

        return true;
    }

    /** The record's current state, as the scalar the column holds. */
    private function currentValue(Model $record): mixed
    {
        return EnumResolver::scalar($record->getAttribute($this->getColumn()));
    }

    private function valueOf(UnitEnum $state): string
    {
        return (string) ($state instanceof BackedEnum ? $state->value : $state->name);
    }

    private function caseOf(string $value): UnitEnum
    {
        foreach (($this->status)::cases() as $case) {
            if ($this->valueOf($case) === $value) {
                return $case;
            }
        }

        throw IllegalTransitionException::notAStatus($value, $this->status);
    }
}

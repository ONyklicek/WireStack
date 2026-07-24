<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireForms\Components\Select;

class TernaryFilter extends Filter
{
    use HasNativeControl;
    use HasSheetOnMobile;

    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    protected ?string $allLabel = null;

    protected bool $nullable = false;

    /** Set the label for the "true" option. */
    public function trueLabel(?string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    /** Set the label for the "false" option. */
    public function falseLabel(?string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    /** Set the label for the "all" (no filter) option. */
    public function allLabel(?string $label): static
    {
        $this->allLabel = $label;

        return $this;
    }

    /** Filter by null/not-null instead of true/false. */
    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Ternary state ('true'/'false') must be coerced to a real boolean (and,
     * when nullable, expanded to a "= false OR IS NULL" branch). Neither can be
     * expressed as a plain column/operator/value planner definition, so always
     * route through apply().
     */
    public function bypassesPlanner(): bool
    {
        return true;
    }

    /**
     * The select submits the option keys 'true'/'false' (and older/URL-seeded
     * state can arrive as '1'/'0', 1/0 or a real bool). Every one of those is
     * coerced to a real boolean here, so the whole query layer — including a
     * ->query() callback — branches on a bool instead of on a string like
     * 'false', which is truthy in PHP.
     *
     * Anything else (including null/'') means "All": the filter is inactive.
     */
    public function normalizeValue(mixed $value): mixed
    {
        if ($value === 'true' || $value === '1' || $value === 1 || $value === true) {
            return true;
        }

        if ($value === 'false' || $value === '0' || $value === 0 || $value === false) {
            return false;
        }

        return null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        $state = $this->normalizeValue($value);

        if ($state === null) {
            return $query;
        }

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $state, $value);
        }

        $column = $this->getColumn();

        if ($state === true) {
            return $query->where($column, true);
        }

        if ($this->nullable) {
            return $query->where(function ($q) use ($column) {
                $q->where($column, false)->orWhereNull($column);
            });
        }

        return $query->where($column, false);
    }

    /**
     * The ternary states as select options. "All" is deliberately not an option:
     * it is the placeholder, i.e. clearing the filter — which lets this render
     * through the same select surface as every other optional select.
     *
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return [
            'true' => $this->getTrueLabel(),
            'false' => $this->getFalseLabel(),
        ];
    }

    public function inlineView(): string
    {
        return 'tables.columns.partials.filter-boolean';
    }

    public function isSelectLike(): bool
    {
        return true;
    }

    public function getFormFields(): array
    {
        // Same option keys as getOptions() — a filter rendered through the
        // generic form-field surface must submit the state the ternary view
        // submits, or the two surfaces disagree about what "false" looks like.
        return [
            Select::make('value')
                ->options($this->getOptions())
                ->placeholder($this->getAllLabel()),
        ];
    }

    protected function filterView(): string
    {
        return 'tables.filters.ternary';
    }

    /**
     * Show the true/false option label in the indicator chip.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        return match ($this->normalizeValue($value)) {
            true => $this->getTrueLabel(),
            false => $this->getFalseLabel(),
            default => null,
        };
    }

    public function getAllLabel(): string
    {
        return $this->allLabel ?? Trans::get('wire-table::messages.filter_all');
    }

    public function getTrueLabel(): string
    {
        return $this->trueLabel ?? Trans::get('wire-table::messages.filter_yes');
    }

    public function getFalseLabel(): string
    {
        return $this->falseLabel ?? Trans::get('wire-table::messages.filter_no');
    }
}

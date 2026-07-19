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
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        if ($this->queryCallback) {
            return ($this->queryCallback)($query, $value);
        }

        $column = $this->getColumn();

        if ($value === 'true' || $value === '1' || $value === true) {
            return $query->where($column, true);
        }

        if ($value === 'false' || $value === '0' || $value === false) {
            if ($this->nullable) {
                return $query->where(function ($q) use ($column) {
                    $q->where($column, false)->orWhereNull($column);
                });
            }

            return $query->where($column, false);
        }

        return $query;
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
        return [
            Select::make('value')
                ->options([
                    '1' => $this->getTrueLabel(),
                    '0' => $this->getFalseLabel(),
                ])
                ->placeholder($this->getAllLabel()),
        ];
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.ternary'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }

    /**
     * Show the true/false option label in the indicator chip.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($value === 'true' || $value === '1' || $value === true) {
            return $this->getTrueLabel();
        }

        if ($value === 'false' || $value === '0' || $value === false) {
            return $this->getFalseLabel();
        }

        return null;
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

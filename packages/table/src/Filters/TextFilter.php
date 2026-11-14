<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Foundation\Concerns\HasDebounce;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Enums\FilterOperator;

/**
 * Free-text filter with a configurable SQL operator.
 *
 * Owns the text-matching behaviour that used to live inline on the table
 * Column (applyTextFilter): substring LIKE
 * by default, plus starts-with / ends-with / exact / comparison operators.
 * The comparison flows through the canonical QueryPlanner via
 * toPlannerDefinitions() so joins/qualification are handled identically to
 * every other filter.
 */
class TextFilter extends Filter
{
    use HasDebounce;

    /**
     * Supported: like, starts_with, ends_with, equals/=, >, >=, <, <=, !=.
     */
    protected string $operator = 'like';

    /** Set the comparison operator (like, starts_with, ends_with, =, >, >=, <, <=, !=). */
    public function operator(string $operator): static
    {
        $this->operator = $operator;

        return $this;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    /** Every comparison a text column can be asked: contains, equals, starts/ends with, their negatives and emptiness. */
    public function supportedOperators(): array
    {
        return FilterOperator::forText();
    }

    /** The operator set with {@see operator()} — `like` (contains) unless the developer chose another. */
    protected function baseOperator(): FilterOperator
    {
        return FilterOperator::resolve($this->operator) ?? FilterOperator::Contains;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $this->normalizeValue($value), $value);
        }

        $operator = $this->getActiveOperator();

        // Crafted/stale state can deliver an array here; guard against
        // "Array to string conversion" in the LIKE/comparison branches.
        if ($operator->requiresValue() && ! is_scalar($value)) {
            return $query;
        }

        return $this->applyOperator($query, $operator, $value, blankIsEmpty: true);
    }

    /**
     * Express the text match as a single planner clause where one clause can say
     * it — LIKE with the value wrapped for substring / prefix / suffix, or a
     * direct comparison — so it plans alongside relation joins + qualification.
     * The negatives and "is empty" keep NULLs or match '' too, which one clause
     * cannot, and go through {@see apply()}.
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        if ($this->queryCallback !== null || $value === '' || $value === null) {
            return [];
        }

        $operator = $this->getActiveOperator();

        if (! $operator->requiresValue() || ! is_scalar($value)) {
            return [];
        }

        $clause = $operator->toPlannerClause($value);

        return $clause === null ? [] : [FilterDefinition::make(
            column: $this->getColumn(),
            operator: $clause[0],
            value: $clause[1],
        )];
    }

    public function getFormFields(): array
    {
        return [
            TextInput::make('value')
                ->placeholder($this->placeholder ?? ''),
        ];
    }
}

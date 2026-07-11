<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireForms\Components\TextInput;

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
    /**
     * Supported: like, starts_with, ends_with, equals/=, >, >=, <, <=, !=.
     */
    protected string $operator = 'like';

    protected ?int $debounce = null;

    public function operator(string $operator): static
    {
        $this->operator = $operator;

        return $this;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Debounce (ms) for the live text input.
     */
    public function debounce(?int $ms): static
    {
        $this->debounce = $ms;

        return $this;
    }

    public function getDebounce(): ?int
    {
        return $this->debounce;
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
            return ($this->queryCallback)($query, $value);
        }

        // Crafted/stale state can deliver an array here; guard against
        // "Array to string conversion" in the LIKE/comparison branches.
        if (! is_scalar($value)) {
            return $query;
        }

        return $this->applyToColumn($query, fn (Builder $q, string $column) => match ($this->operator) {
            'equals', '=' => $q->where($column, $value),
            'starts_with' => $q->where($column, 'like', "$value%"),
            'ends_with' => $q->where($column, 'like', "%$value"),
            '>', '>=', '<', '<=', '!=' => $q->where($column, $this->operator, $value),
            default => $q->where($column, 'like', "%$value%"),
        });
    }

    /**
     * Express the text match as a single planner clause: the operator maps to
     * LIKE (with the value wrapped for substring / prefix / suffix) or a direct
     * comparison, so it plans alongside relation joins + qualification.
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        if ($this->queryCallback !== null || ! is_scalar($value) || $value === '') {
            return [];
        }

        [$operator, $planValue] = match ($this->operator) {
            'equals', '=' => ['=', $value],
            'starts_with' => ['LIKE', "$value%"],
            'ends_with' => ['LIKE', "%$value"],
            '>', '>=', '<', '<=', '!=' => [$this->operator, $value],
            default => ['LIKE', "%$value%"],
        };

        return [FilterDefinition::make(
            column: $this->getColumn(),
            operator: $operator,
            value: $planValue,
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

<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Support\QueryBuilderState;

/**
 * Conditions combined with AND / OR — "all of: status is paid, and any of:
 * customer contains Acme, customer contains Beta". The advanced filter of
 * Linear, Airtable or Filament's query builder.
 *
 * It owns no comparison of its own. Each condition is a field the developer
 * offered as a {@see constraints()} entry, and each entry is an ordinary filter
 * (TextFilter, SelectFilter, DateFilter, TernaryFilter) — so "does not contain"
 * here keeps NULLs, crosses a relation and words its chip exactly as it does in
 * a header, because it is the same code doing it. The builder only arranges:
 * which filter, which operator, which value, and how the results combine.
 *
 *     QueryBuilderFilter::make('advanced')->constraints([
 *         TextFilter::make('customer'),
 *         SelectFilter::make('status')->options([...]),
 *         DateFilter::make('issued_at'),
 *     ])
 *
 * The state is `{combinator, rules}` ({@see QueryBuilderState}): saved views keep
 * it; it is too nested for the URL and is not put there.
 */
class QueryBuilderFilter extends Filter
{
    /** @var array<string, Filter> keyed by the filter's name */
    protected array $constraints = [];

    /**
     * The fields a condition may be about, each an ordinary filter. Every one
     * offers all the operators its type supports unless it was given its own
     * list; a select becomes a multiple choice ("is any of").
     *
     * @param  array<int, Filter>  $filters
     */
    public function constraints(array $filters): static
    {
        $this->constraints = [];

        foreach ($filters as $filter) {
            if ($filter instanceof SelectFilter && ! $filter->isMultiple()) {
                $filter->multiple();
            }

            if ($filter->getOperators() === [] && $filter->supportedOperators() !== []) {
                $filter->operators();
            }

            $this->constraints[$filter->getName()] = $filter->statePathPrefix('tableState.filters.'.$this->name);
        }

        return $this;
    }

    /**
     * The fields this user may build conditions on. A constraint that is hidden
     * or not authorized is left out everywhere — the field list, the query and
     * the chip — so a crafted state naming it narrows nothing.
     *
     * @return array<string, Filter>
     */
    public function getConstraints(): array
    {
        return array_filter($this->constraints, static fn (Filter $filter): bool => $filter->canView());
    }

    public function getConstraint(?string $field): ?Filter
    {
        return $field === null ? null : ($this->getConstraints()[$field] ?? null);
    }

    /** The field a new condition starts on: the first one offered. */
    public function getFirstConstraint(): ?Filter
    {
        $constraints = $this->getConstraints();

        return $constraints === [] ? null : $constraints[array_key_first($constraints)];
    }

    /** A row of conditions needs the panel's full width. */
    public function spansFullWidth(): bool
    {
        return true;
    }

    /** No single clause says a tree of conditions; it always constrains the query itself. */
    public function bypassesPlanner(): bool
    {
        return true;
    }

    public function toPlannerDefinitions(mixed $value): array
    {
        return [];
    }

    /** The whole `{combinator, rules}` tree is the value — it is not wrapped. */
    public function extractValue(mixed $raw): mixed
    {
        return $this->hasActiveRule($raw) ? QueryBuilderState::normalize($raw) : null;
    }

    public function wrapValue(mixed $value): mixed
    {
        return QueryBuilderState::normalize($value);
    }

    public function emptyState(): mixed
    {
        return QueryBuilderState::empty();
    }

    /** Too nested for a query string; saved views carry it instead. */
    public function getQueryStringFields(): array
    {
        return [];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $value, $value);
        }

        $this->applyGroup($query, QueryBuilderState::normalize($value));

        return $query;
    }

    /**
     * Whether any condition in the state would narrow the rows: a known field,
     * and a value — or an operator that needs none ("is empty").
     */
    public function hasActiveRule(mixed $state): bool
    {
        foreach (QueryBuilderState::normalize($state)['rules'] as $rule) {
            if (isset($rule['rules']) ? $this->hasActiveRule($rule) : $this->ruleIsActive($rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Customer does not contain Acme and Status is any of Paid" — the conditions
     * in the words the chips of the constraint filters use.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        $text = $this->describeGroup(QueryBuilderState::normalize($value));

        return $text === '' ? null : $text;
    }

    public function getIndicator(mixed $raw): ?string
    {
        $text = $this->getIndicatorValueLabel($raw);

        if ($text === null) {
            return null;
        }

        if ($this->indicator !== null) {
            return parent::getIndicator($raw);
        }

        return $text;
    }

    protected function filterView(): string
    {
        return 'tables.filters.query-builder';
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array{combinator: string, rules: list<array<string, mixed>>}  $group
     */
    protected function applyGroup(Builder $query, array $group): void
    {
        $rules = array_values(array_filter(
            $group['rules'],
            fn (array $rule): bool => isset($rule['rules']) ? $this->hasActiveRule($rule) : $this->ruleIsActive($rule),
        ));

        if ($rules === []) {
            return;
        }

        $boolean = $group['combinator'] === 'or' ? 'or' : 'and';

        $query->where(function (Builder $inner) use ($rules, $boolean): void {
            foreach ($rules as $rule) {
                $inner->where(function (Builder $condition) use ($rule): void {
                    if (isset($rule['rules'])) {
                        /** @var array{combinator: string, rules: list<array<string, mixed>>} $rule */
                        $this->applyGroup($condition, $rule);

                        return;
                    }

                    $this->applyRule($condition, $rule);
                }, boolean: $boolean);
            }
        });
    }

    /**
     * One condition: the constraint filter, set to the rule's operator, applied
     * to the rule's value — qualified against the base table, as a header
     * filter is, so a join the planner added cannot make the column ambiguous.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $rule
     */
    protected function applyRule(Builder $query, array $rule): void
    {
        // applyGroup() only hands over active rules, and a rule is active only
        // on a field this user may see — the constraint is always there.
        $constraint = $this->getConstraint($rule['field']);
        assert($constraint !== null);

        $state = ['value' => $rule['value'], 'operator' => $rule['operator']];
        $filter = $constraint->forState($state);

        if ($filter->getRelation() === null && ! str_contains($filter->getColumn(), '.')) {
            $filter = (clone $filter)->column($query->qualifyColumn($filter->getColumn()));
        }

        $filter->apply($query, $filter->extractValue($state));
    }

    /** @param  array<string, mixed>  $rule */
    protected function ruleIsActive(array $rule): bool
    {
        $constraint = $this->getConstraint($rule['field'] ?? null);

        if ($constraint === null) {
            return false;
        }

        $value = $constraint->extractValue(['value' => $rule['value'] ?? null, 'operator' => $rule['operator'] ?? null]);

        return ! ($value === null || $value === '' || $value === []);
    }

    /** @param  array{combinator: string, rules: list<array<string, mixed>>}  $group */
    protected function describeGroup(array $group): string
    {
        $parts = [];

        foreach ($group['rules'] as $rule) {
            if (isset($rule['rules'])) {
                /** @var array{combinator: string, rules: list<array<string, mixed>>} $rule */
                $inner = $this->describeGroup($rule);
                if ($inner !== '') {
                    $parts[] = '('.$inner.')';
                }

                continue;
            }

            if (! $this->ruleIsActive($rule)) {
                continue;
            }

            $constraint = $this->getConstraint($rule['field']);
            $chip = $constraint?->getIndicator(['value' => $rule['value'], 'operator' => $rule['operator']]);

            if ($chip !== null) {
                // "Customer: contains Acme" → "Customer contains Acme" inside a sentence.
                $parts[] = preg_replace('/: /', ' ', $chip, 1);
            }
        }

        $joiner = ' '.Trans::get('wire-table::messages.query_builder_'.($group['combinator'] === 'or' ? 'or' : 'and')).' ';

        return implode($joiner, $parts);
    }
}

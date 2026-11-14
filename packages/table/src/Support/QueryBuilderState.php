<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Filters\QueryBuilderFilter;

/**
 * The edits a {@see QueryBuilderFilter} state takes
 * — add a condition, add a group, remove one — as pure functions over its array.
 *
 * The state is `{combinator, rules}`; a rule is `{field, operator, value}` and a
 * group is `{combinator, rules}` of rules, one level deep. That is the shape a
 * person can still read back ("all of: status is paid, and any of: customer
 * contains Acme or customer contains Beta"); a builder that nests without limit
 * is one nobody can check. The arrays stay plain so the Livewire state, the URL-
 * less saved views and the tests all hold the same thing.
 */
final class QueryBuilderState
{
    /**
     * The most conditions a tree holds, groups included. The state travels in
     * the Livewire snapshot and every condition is a constraint the query
     * authorizes and applies, so a crafted payload of ten thousand is ten
     * thousand of each; fifty is more than anyone reads back.
     */
    public const MAX_RULES = 50;

    /** The longest text value a condition keeps, in characters. */
    public const MAX_VALUE_LENGTH = 1000;

    /** The most values a multiple-choice condition keeps. */
    public const MAX_VALUES = 100;

    /** @return array{combinator: string, rules: list<array<string, mixed>>} */
    public static function empty(): array
    {
        return ['combinator' => 'and', 'rules' => []];
    }

    /**
     * The state as it should be: a known combinator, rules that are arrays,
     * groups only at the top level, and no more than {@see MAX_RULES}
     * conditions with values no longer than {@see MAX_VALUE_LENGTH}. Anything
     * else (a crafted payload, a stale saved view) is dropped or cut rather
     * than trusted.
     *
     * @return array{combinator: string, rules: list<array<string, mixed>>}
     */
    public static function normalize(mixed $state, int $depth = 0, ?int &$budget = null): array
    {
        $state = is_array($state) ? $state : [];
        $budget ??= self::MAX_RULES;
        $rules = [];

        foreach (is_array($state['rules'] ?? null) ? $state['rules'] : [] as $rule) {
            if ($budget <= 0) {
                break;
            }

            if (! is_array($rule)) {
                continue;
            }

            if (array_key_exists('rules', $rule)) {
                if ($depth === 0) {
                    $rules[] = self::normalize($rule, 1, $budget);
                }

                continue;
            }

            $budget--;
            $rules[] = [
                'field' => is_string($rule['field'] ?? null) ? $rule['field'] : null,
                'operator' => is_string($rule['operator'] ?? null) ? $rule['operator'] : null,
                'value' => self::value($rule['value'] ?? null),
            ];
        }

        return [
            'combinator' => ($state['combinator'] ?? 'and') === 'or' ? 'or' : 'and',
            'rules' => $rules,
        ];
    }

    /**
     * A condition's value, cut to size: text to {@see MAX_VALUE_LENGTH}
     * characters, a list to {@see MAX_VALUES} scalar entries, anything deeper
     * dropped — no constraint takes a nested value.
     */
    private static function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return mb_substr($value, 0, self::MAX_VALUE_LENGTH);
        }

        if (is_array($value)) {
            $list = array_values(array_filter($value, is_scalar(...)));

            return array_map(
                static fn (mixed $item): mixed => is_string($item) ? mb_substr($item, 0, self::MAX_VALUE_LENGTH) : $item,
                array_slice($list, 0, self::MAX_VALUES),
            );
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * How many conditions the tree holds, counting those inside groups.
     *
     * @param  array<string, mixed>  $state
     */
    public static function ruleCount(array $state): int
    {
        $count = 0;

        foreach (self::normalize($state)['rules'] as $rule) {
            $count += isset($rule['rules']) ? count($rule['rules']) : 1;
        }

        return $count;
    }

    /**
     * Append an empty condition, at the top level or inside group `$group`.
     *
     * @param  array<string, mixed>  $state
     * @return array{combinator: string, rules: list<array<string, mixed>>}
     */
    public static function addRule(array $state, ?string $field, ?string $operator, ?int $group = null): array
    {
        $state = self::normalize($state);
        $rule = ['field' => $field, 'operator' => $operator, 'value' => null];

        if (self::ruleCount($state) >= self::MAX_RULES) {
            return $state;
        }

        if ($group === null) {
            $state['rules'][] = $rule;
        } elseif (isset($state['rules'][$group]['rules'])) {
            $state['rules'][$group]['rules'][] = $rule;
        }

        return $state;
    }

    /**
     * Append a group holding one empty condition. Its combinator is the
     * opposite of the top level's: a group exists to say "any of" inside an
     * "all of", or the other way round.
     *
     * @param  array<string, mixed>  $state
     * @return array{combinator: string, rules: list<array<string, mixed>>}
     */
    public static function addGroup(array $state, ?string $field, ?string $operator): array
    {
        $state = self::normalize($state);

        if (self::ruleCount($state) >= self::MAX_RULES) {
            return $state;
        }

        $state['rules'][] = [
            'combinator' => $state['combinator'] === 'and' ? 'or' : 'and',
            'rules' => [['field' => $field, 'operator' => $operator, 'value' => null]],
        ];

        return $state;
    }

    /**
     * Remove condition `$index` (inside group `$group` when given). A group left
     * empty goes with its last condition.
     *
     * @param  array<string, mixed>  $state
     * @return array{combinator: string, rules: list<array<string, mixed>>}
     */
    public static function remove(array $state, int $index, ?int $group = null): array
    {
        $state = self::normalize($state);

        if ($group === null) {
            unset($state['rules'][$index]);
        } elseif (isset($state['rules'][$group]['rules'])) {
            unset($state['rules'][$group]['rules'][$index]);
            $state['rules'][$group]['rules'] = array_values($state['rules'][$group]['rules']);

            if ($state['rules'][$group]['rules'] === []) {
                unset($state['rules'][$group]);
            }
        }

        $state['rules'] = array_values($state['rules']);

        return $state;
    }
}

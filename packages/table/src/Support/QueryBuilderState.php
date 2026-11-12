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
    /** @return array{combinator: string, rules: list<array<string, mixed>>} */
    public static function empty(): array
    {
        return ['combinator' => 'and', 'rules' => []];
    }

    /**
     * The state as it should be: a known combinator, rules that are arrays, and
     * groups only at the top level. Anything else (a crafted payload, a stale
     * saved view) is dropped rather than trusted.
     *
     * @return array{combinator: string, rules: list<array<string, mixed>>}
     */
    public static function normalize(mixed $state, int $depth = 0): array
    {
        $state = is_array($state) ? $state : [];
        $rules = [];

        foreach (is_array($state['rules'] ?? null) ? $state['rules'] : [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (array_key_exists('rules', $rule)) {
                if ($depth === 0) {
                    $rules[] = self::normalize($rule, 1);
                }

                continue;
            }

            $rules[] = [
                'field' => is_string($rule['field'] ?? null) ? $rule['field'] : null,
                'operator' => is_string($rule['operator'] ?? null) ? $rule['operator'] : null,
                'value' => $rule['value'] ?? null,
            ];
        }

        return [
            'combinator' => ($state['combinator'] ?? 'and') === 'or' ? 'or' : 'and',
            'rules' => $rules,
        ];
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

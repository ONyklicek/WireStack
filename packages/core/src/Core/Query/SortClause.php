<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Immutable representation of a sort clause in a query plan.
 */
final readonly class SortClause
{
    /** Sort direction, normalised to a safe `asc`/`desc` keyword. */
    public string $direction;

    /** NULLS position, normalised to a safe `FIRST`/`LAST` keyword or null. */
    public ?string $nullsPosition;

    /**
     * @param  string  $column  The column or SQL expression to sort by
     * @param  string  $direction  Sort direction (asc/desc)
     * @param  string|null  $tableAlias  Table alias if through a join
     * @param  string|null  $sqlExpression  Raw SQL expression
     * @param  bool  $isRelation  Whether this sort goes through a relation
     * @param  string|null  $nullsPosition  NULLS FIRST or NULLS LAST (null = DB default)
     */
    public function __construct(
        public string $column,
        string $direction = 'asc',
        public ?string $tableAlias = null,
        public ?string $sqlExpression = null,
        public bool $isRelation = false,
        ?string $nullsPosition = null,
    ) {
        // Direction and NULLS position are interpolated into orderByRaw for SQL-expression
        // and NULLS sorts, so they are normalised here — the single owner of a sort clause —
        // against a fixed keyword allow-list. Any untrusted value collapses to a safe default.
        $this->direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        // Accept both the bare keyword ("LAST") and the full "NULLS LAST" form, storing the
        // bare keyword — ApplySorting prepends "NULLS", so storing the prefix too would emit
        // an invalid "NULLS NULLS LAST".
        $normalisedNulls = preg_replace('/^NULLS\s+/', '', strtoupper(trim((string) $nullsPosition)));

        $this->nullsPosition = match ($normalisedNulls) {
            'FIRST' => 'FIRST',
            'LAST' => 'LAST',
            default => null,
        };
    }

    /**
     * Get the fully qualified column reference for SQL.
     */
    public function getQualifiedColumn(): string
    {
        if ($this->sqlExpression !== null) {
            return $this->sqlExpression;
        }

        if ($this->tableAlias !== null) {
            return "{$this->tableAlias}.{$this->column}";
        }

        return $this->column;
    }
}

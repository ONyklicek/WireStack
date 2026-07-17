<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Central registry of joins — prevents duplicates, manages aliases.
 *
 * Default: LEFT JOIN. Inner join only when explicitly requested.
 */
final class JoinRegistry
{
    /** @var array<string, JoinClause> */
    private array $joins = [];

    private readonly AliasGenerator $aliasGenerator;

    public function __construct(?AliasGenerator $aliasGenerator = null)
    {
        $this->aliasGenerator = $aliasGenerator ?? new AliasGenerator;
    }

    /**
     * Register a join. Returns the alias for the joined table.
     *
     * @param  array<int, string>  $relationPath
     */
    public function registerJoin(
        string $baseTable,
        array $relationPath,
        string $joinTable,
        string $firstColumn,
        string $operator,
        string $secondColumn,
        string $type = 'left',
        ?JoinScope $scope = null,
    ): string {
        $alias = $this->aliasGenerator->generate($baseTable, $relationPath);
        $key = $alias;

        if (! isset($this->joins[$key])) {
            $this->joins[$key] = new JoinClause(
                table: $joinTable,
                alias: $alias,
                firstColumn: $firstColumn,
                operator: $operator,
                secondColumn: $secondColumn,
                type: $type,
                scope: $scope,
            );
        }

        return $alias;
    }

    public function hasJoin(string $alias): bool
    {
        return isset($this->joins[$alias]);
    }

    public function getJoin(string $alias): ?JoinClause
    {
        return $this->joins[$alias] ?? null;
    }

    /**
     * @return array<string, JoinClause>
     */
    public function getAllJoins(): array
    {
        return $this->joins;
    }

    public function getAliasGenerator(): AliasGenerator
    {
        return $this->aliasGenerator;
    }

    public function isEmpty(): bool
    {
        return $this->joins === [];
    }

    public function count(): int
    {
        return count($this->joins);
    }

    public function reset(): void
    {
        $this->joins = [];
        $this->aliasGenerator->reset();
    }
}

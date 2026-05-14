<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * Deterministic alias generation for joined tables.
 *
 * Pattern: {parent_table}_{relation_name}
 * Nested: {root}_{relation1}_{relation2}
 */
final class AliasGenerator
{
    /** @var array<string, string> */
    private array $generated = [];

    /**
     * Generate a deterministic alias for a relation join.
     *
     * @param  array<int, string>  $relationPath  Path segments (e.g., ['users', 'company'])
     */
    public function generate(string $baseTable, array $relationPath): string
    {
        $key = $baseTable.'.'.implode('.', $relationPath);

        if (isset($this->generated[$key])) {
            return $this->generated[$key];
        }

        $alias = $baseTable.'_'.implode('_', $relationPath);

        // Truncate if too long (most DBs limit identifiers to 64 chars)
        if (strlen($alias) > 60) {
            $alias = substr($alias, 0, 52).'_'.substr(md5($key), 0, 7);
        }

        $this->generated[$key] = $alias;

        return $alias;
    }

    /**
     * Get alias for an already-registered path, or null if not registered.
     *
     * @param  array<int, string>  $relationPath
     */
    public function getAlias(string $baseTable, array $relationPath): ?string
    {
        $key = $baseTable.'.'.implode('.', $relationPath);

        return $this->generated[$key] ?? null;
    }

    /**
     * Check if a path has already been aliased.
     *
     * @param  array<int, string>  $relationPath
     */
    public function hasAlias(string $baseTable, array $relationPath): bool
    {
        $key = $baseTable.'.'.implode('.', $relationPath);

        return isset($this->generated[$key]);
    }

    /**
     * @return array<string, string>
     */
    public function getAllAliases(): array
    {
        return $this->generated;
    }

    public function reset(): void
    {
        $this->generated = [];
    }
}

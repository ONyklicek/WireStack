<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Capabilities;

use NyonCode\WireCore\Core\Metadata\AccessorMetadata;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;

/**
 * Resolves capabilities from metadata and component configuration.
 */
final class CapabilityResolver
{
    /**
     * Resolve capabilities for a column/field based on its metadata.
     *
     * @param  array<int, Capability>  $explicit  Capabilities explicitly set by user
     */
    public function resolve(
        ?ColumnMetadata $columnMetadata = null,
        ?AccessorMetadata $accessorMetadata = null,
        array $explicit = [],
    ): CapabilitySet {
        $capabilities = $explicit;

        if ($columnMetadata !== null) {
            $capabilities = $this->resolveFromColumnMetadata($columnMetadata, $capabilities);
        }

        if ($accessorMetadata !== null) {
            $capabilities = $this->resolveFromAccessorMetadata($accessorMetadata, $capabilities);
        }

        return new CapabilitySet(...$capabilities);
    }

    /**
     * @param  array<int, Capability>  $capabilities
     * @return array<int, Capability>
     */
    private function resolveFromColumnMetadata(ColumnMetadata $metadata, array $capabilities): array
    {
        // DB columns are searchable, sortable, filterable by default
        if ($metadata->dbColumn !== null) {
            $capabilities[] = Capability::Searchable;
            $capabilities[] = Capability::Sortable;
            $capabilities[] = Capability::Filterable;
            $capabilities[] = Capability::Dehydrated;
            $capabilities[] = Capability::Hydrated;
        }

        // SQL expressions can be searched/sorted/filtered
        if ($metadata->sqlExpression !== null) {
            $capabilities[] = Capability::SqlExpression;
            $capabilities[] = Capability::Searchable;
            $capabilities[] = Capability::Sortable;
            $capabilities[] = Capability::Filterable;
        }

        // Runtime-only accessors cannot be searched/sorted in SQL
        if ($metadata->isRuntimeOnly()) {
            $capabilities[] = Capability::RuntimeOnly;
        }

        return $capabilities;
    }

    /**
     * @param  array<int, Capability>  $capabilities
     * @return array<int, Capability>
     */
    private function resolveFromAccessorMetadata(AccessorMetadata $metadata, array $capabilities): array
    {
        if ($metadata->runtimeOnly) {
            $capabilities[] = Capability::RuntimeOnly;
        }

        if ($metadata->sqlExpression !== null) {
            $capabilities[] = Capability::SqlExpression;
            $capabilities[] = Capability::Searchable;
            $capabilities[] = Capability::Sortable;
        }

        return $capabilities;
    }
}

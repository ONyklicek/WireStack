<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Capabilities;

/**
 * Shared capabilities across tables, forms, and infolists.
 */
enum Capability: string
{
    case Searchable = 'searchable';
    case Sortable = 'sortable';
    case Filterable = 'filterable';
    case Editable = 'editable';
    case Dehydrated = 'dehydrated';
    case Hydrated = 'hydrated';
    case RuntimeOnly = 'runtime_only';
    case RequiresHydration = 'requires_hydration';
    case Aggregateable = 'aggregateable';
    case SqlExpression = 'sql_expression';
}

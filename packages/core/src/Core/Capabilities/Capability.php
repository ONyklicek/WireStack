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

    // Data-source aspects (V2.0). A source declares what it can answer, and the
    // engine honours only what is declared — an aspect a QueryPlan asks for and
    // the source has not claimed is an UnsupportedQueryAspectException, never a
    // silently wrong result.
    case Joinable = 'joinable';
    case Paginable = 'paginable';
    case SubRows = 'sub_rows';
    case ChangeToken = 'change_token';
}

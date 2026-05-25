<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Exporter
{
    /**
     * Export the query results to a downloadable response.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, \NyonCode\WireTable\Columns\Column>  $columns
     * @param  string  $fileName
     */
    public function export(Builder $query, array $columns, string $fileName): StreamedResponse;
}

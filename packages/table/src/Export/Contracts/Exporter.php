<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Exporter
{
    /**
     * Export the query results to a downloadable response.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     */
    public function export(Builder $query, array $columns, string $fileName): StreamedResponse;
}

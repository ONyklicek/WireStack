<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import\Contracts;

interface Importer
{
    /**
     * Read the file into header-keyed rows.
     *
     * Each yielded row maps the file's header names to that row's cell values,
     * so downstream mapping is by header rather than position.
     *
     * @return iterable<int, array<string, string>>
     */
    public function rows(string $filePath): iterable;
}

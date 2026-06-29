<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

#[Name('database-query')]
#[Description('Execute a read-only (SELECT) SQL query against the database and return the rows. Disabled by default; enable with WIRE_BOOST_DATABASE_QUERY=true.')]
class DatabaseQuery extends BoostTool
{
    public function handle(Request $request): Response
    {
        if (! config('wire-boost.tools.database_query', false)) {
            return $this->json(['error' => 'The database-query tool is disabled. Set WIRE_BOOST_DATABASE_QUERY=true to enable it.']);
        }

        $query = trim((string) $request->get('query'));

        if (! $this->isReadOnly($query)) {
            return $this->json(['error' => 'Only read-only SELECT queries are allowed.']);
        }

        try {
            $connection = trim((string) $request->get('connection'));
            $rows = DB::connection($connection !== '' ? $connection : null)->select($query);

            return $this->json([
                'count' => count($rows),
                'rows' => array_map(static fn (object $row): array => (array) $row, $rows),
            ]);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }
    }

    private function isReadOnly(string $query): bool
    {
        $normalized = ltrim(strtolower($query));

        if (! str_starts_with($normalized, 'select') && ! str_starts_with($normalized, 'with')) {
            return false;
        }

        foreach (['insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'create', 'replace', 'grant'] as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/', $normalized) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('A SELECT statement to execute.')->required(),
            'connection' => $schema->string()->description('Optional database connection name.'),
        ];
    }
}

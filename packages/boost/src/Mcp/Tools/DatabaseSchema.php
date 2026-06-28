<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

#[Name('database-schema')]
#[Description('Read the database schema: the list of tables and, for each, its columns with types and nullability. Pass a table name to inspect a single table.')]
class DatabaseSchema extends BoostTool
{
    public function handle(Request $request): Response
    {
        $connection = trim((string) $request->get('connection'));
        $only = trim((string) $request->get('table'));

        try {
            $builder = $connection !== '' ? Schema::connection($connection) : Schema::connection(null);

            $tables = array_map(
                static fn (array $table): string => (string) $table['name'],
                $builder->getTables(),
            );

            if ($only !== '') {
                $tables = array_values(array_filter($tables, static fn (string $name): bool => $name === $only));
            }

            $schema = [];

            foreach ($tables as $table) {
                $schema[$table] = array_map(static fn (array $column): array => [
                    'name' => $column['name'],
                    'type' => $column['type'],
                    'nullable' => $column['nullable'],
                ], $builder->getColumns($table));
            }

            return $this->json(['tables' => $schema]);
        } catch (Throwable $e) {
            return $this->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string()->description('Optional table name to inspect a single table.'),
            'connection' => $schema->string()->description('Optional database connection name.'),
        ];
    }
}

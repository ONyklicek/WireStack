<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\DocsIndex;

#[Name('search-wire-docs')]
#[Description('Search the wireStack documentation corpus (shipped guidelines and skills, plus any configured Markdown) and return the most relevant documents with a matching snippet. Use this before writing wire code to confirm conventions.')]
class SearchDocs extends BoostTool
{
    public function __construct(private DocsIndex $docs) {}

    public function handle(Request $request): Response
    {
        $query = (string) $request->get('query');
        $package = trim((string) $request->get('package'));
        $limit = (int) ($request->get('limit') ?? 5);

        $results = $this->docs->search($query, $package !== '' ? $package : null, $limit);

        return $this->json([
            'query' => $query,
            'count' => count($results),
            'results' => $results,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text search query, e.g. "badge column color" or "form validation".')
                ->required(),
            'package' => $schema->string()
                ->description('Optional package filter, e.g. "wire-table" or "wire-forms".'),
            'limit' => $schema->integer()
                ->description('Maximum number of documents to return (default 5).'),
        ];
    }
}

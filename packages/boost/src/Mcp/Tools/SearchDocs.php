<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\Docs\DocsIndex;

#[Name('search-wire-docs')]
#[Description('Search the full wireStack documentation and return the most relevant sections, each with an id, breadcrumb and snippet. Pass an id to `fetch-wire-doc` to read the whole section. Use this before writing wire code to confirm conventions and APIs.')]
class SearchDocs extends BoostTool
{
    public function __construct(private DocsIndex $docs) {}

    protected function run(Request $request): Response
    {
        $query = (string) $request->get('query');
        $package = trim((string) $request->get('package'));
        $limit = (int) ($request->get('limit') ?? 5);

        $results = $this->docs->search($query, $package !== '' ? $package : null, $limit);

        return $this->json([
            'query' => $query,
            'count' => count($results),
            'results' => $results,
            'hint' => $results === []
                ? 'No sections matched. Try fewer or more general terms, or drop the package filter.'
                : 'Call fetch-wire-doc with a result id to read the full section.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text query, e.g. "badge column color" or "validate a repeater".')
                ->required(),
            'package' => $schema->string()
                ->description('Optional package filter: "wire-table", "wire-forms", "wire-core", "wire-sortable" or "wire-boost" (the "wire-" prefix is optional).'),
            'limit' => $schema->integer()
                ->description('Maximum number of sections to return (default 5).'),
        ];
    }
}

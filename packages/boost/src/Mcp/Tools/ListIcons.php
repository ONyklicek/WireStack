<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireCore\Foundation\Icons\IconManager;

#[Name('list-icons')]
#[Description('List the icon names registered with the wire IconManager, optionally filtered by a substring. Use these names with ->icon() on actions, columns, fields and entries.')]
class ListIcons extends BoostTool
{
    public function __construct(private IconManager $icons) {}

    protected function run(Request $request): Response
    {
        $names = $this->icons->allNames();
        $filter = trim((string) $request->get('filter'));

        if ($filter !== '') {
            $names = array_values(array_filter(
                $names,
                static fn (string $name): bool => str_contains($name, $filter),
            ));
        }

        return $this->json([
            'count' => count($names),
            'icons' => $names,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description('Optional case-sensitive substring to filter icon names.'),
        ];
    }
}

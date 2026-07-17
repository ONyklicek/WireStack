<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Exceptions\UnresolvableComponentException;
use NyonCode\WireBoost\Support\TypeCatalog;

#[Name('list-component-types')]
#[Description('List the built-in wireStack component types for a category (columns, filters, fields, actions, infolist-entries, widgets, modals), with each type short name, class and summary.')]
class ListComponentTypes extends BoostTool
{
    public function __construct(private TypeCatalog $catalog) {}

    protected function run(Request $request): Response
    {
        $category = (string) $request->get('category');

        if (! $this->catalog->has($category)) {
            throw UnresolvableComponentException::unknownCategory($category, $this->catalog->categories());
        }

        return $this->json([
            'category' => $category,
            'types' => $this->catalog->types($category),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->enum($this->catalog->categories())
                ->description('The component category to list.')
                ->required(),
        ];
    }
}

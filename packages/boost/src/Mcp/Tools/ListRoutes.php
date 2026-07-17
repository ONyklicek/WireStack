<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Routing\Router;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-routes')]
#[Description('List the application HTTP routes with their methods, URI, name and action, optionally filtered by a URI substring.')]
class ListRoutes extends BoostTool
{
    public function __construct(private Router $router) {}

    protected function run(Request $request): Response
    {
        $filter = trim((string) $request->get('filter'));
        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if ($filter !== '' && ! str_contains($uri, $filter)) {
                continue;
            }

            $routes[] = [
                'methods' => $route->methods(),
                'uri' => $uri,
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ];
        }

        return $this->json([
            'count' => count($routes),
            'routes' => $routes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()->description('Optional substring to filter route URIs.'),
        ];
    }
}

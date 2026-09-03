<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Support\ModuleReflector;

#[Name('describe-module')]
#[Description('List the application\'s domain modules — id, dependencies, and the resources, dashboards and navigation group each declares. Omit `module` for all of them.')]
class DescribeModule extends BoostTool
{
    public function __construct(private ModuleReflector $reflector) {}

    protected function run(Request $request): Response
    {
        $id = $request->get('module');

        if ($id === null || $id === '') {
            return $this->json(['modules' => $this->reflector->all()]);
        }

        $described = $this->reflector->describe((string) $id);

        if ($described !== null) {
            return $this->json($described);
        }

        // The developer is usually one typo away, and the registered ids are the
        // shortest way to show it.
        return $this->json([
            'error' => "No domain module is registered under [{$id}].",
            'registered' => $this->reflector->ids(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module id, e.g. "billing". Omit for every registered module.'),
        ];
    }
}

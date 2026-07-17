<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('wire-config')]
#[Description('Read the effective wireStack configuration. Returns the wire-core, wire-forms, wire-table and wire-sortable config namespaces, or a single dotted key when provided.')]
class WireConfig extends BoostTool
{
    private const NAMESPACES = ['wire-core', 'wire-forms', 'wire-table', 'wire-sortable', 'wire-boost'];

    protected function run(Request $request): Response
    {
        $key = trim((string) $request->get('key'));

        if ($key !== '') {
            return $this->json([
                'key' => $key,
                'value' => config($key),
            ]);
        }

        $config = [];

        foreach (self::NAMESPACES as $namespace) {
            if (app('config')->has($namespace)) {
                $config[$namespace] = config($namespace);
            }
        }

        return $this->json($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()
                ->description('Optional dotted config key, e.g. "wire-table.defaults.per_page".'),
        ];
    }
}

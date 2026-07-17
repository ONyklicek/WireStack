<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-absolute-url')]
#[Description('Convert a relative path into an absolute URL using the application URL, so generated links are valid.')]
class GetAbsoluteUrl extends BoostTool
{
    protected function run(Request $request): Response
    {
        $path = (string) $request->get('path');

        return $this->json([
            'path' => $path,
            'url' => url($path),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('A relative path or URI, e.g. "/users".')->required(),
        ];
    }
}

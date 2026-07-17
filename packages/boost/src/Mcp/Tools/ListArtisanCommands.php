<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-artisan-commands')]
#[Description('List the available Artisan commands with their descriptions, optionally filtered by a name substring.')]
class ListArtisanCommands extends BoostTool
{
    public function __construct(private Kernel $kernel) {}

    protected function run(Request $request): Response
    {
        $this->kernel->bootstrap();
        $filter = trim((string) $request->get('filter'));

        $commands = [];

        foreach ($this->kernel->all() as $name => $command) {
            if ($filter !== '' && ! str_contains($name, $filter)) {
                continue;
            }

            $commands[$name] = $command->getDescription();
        }

        ksort($commands);

        return $this->json([
            'count' => count($commands),
            'commands' => $commands,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()->description('Optional substring to filter command names, e.g. "wire".'),
        ];
    }
}

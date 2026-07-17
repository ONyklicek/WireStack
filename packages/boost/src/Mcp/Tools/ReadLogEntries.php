<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Mcp\Tools\Concerns\ReadsLogFile;

#[Name('read-log-entries')]
#[Description('Read the last N lines from the application log file.')]
class ReadLogEntries extends BoostTool
{
    use ReadsLogFile;

    protected function run(Request $request): Response
    {
        $limit = (int) ($request->get('entries') ?? 25);
        $lines = $this->tailLines($limit);

        return $this->json([
            'file' => $this->logFile(),
            'count' => count($lines),
            'entries' => $lines,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entries' => $schema->integer()->description('Number of trailing log lines to return (default 25).'),
        ];
    }
}

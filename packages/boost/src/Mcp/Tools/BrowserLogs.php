<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('browser-logs')]
#[Description('Read the most recent browser console log entries captured to the configured wire-boost browser log file.')]
class BrowserLogs extends BoostTool
{
    protected function run(Request $request): Response
    {
        if (! config('wire-boost.tools.browser_logs', true)) {
            return Response::error('The browser-logs tool is disabled.');
        }

        $path = (string) config('wire-boost.browser_logs.path', storage_path('wire-boost/browser.log'));
        $max = (int) ($request->get('entries') ?? config('wire-boost.browser_logs.max_entries', 50));

        if (! is_file($path)) {
            return $this->json([
                'file' => $path,
                'count' => 0,
                'entries' => [],
                'message' => 'No browser log file found yet.',
            ]);
        }

        $lines = preg_split('/\R/', (string) file_get_contents($path)) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        $lines = array_slice($lines, -max(1, $max));

        return $this->json([
            'file' => $path,
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
            'entries' => $schema->integer()->description('Maximum number of trailing entries to return.'),
        ];
    }
}

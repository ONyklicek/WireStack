<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use NyonCode\WireBoost\Mcp\Tools\Concerns\ReadsLogFile;

#[Name('last-error')]
#[Description('Read the most recent error entry from the application log file.')]
class LastError extends BoostTool
{
    use ReadsLogFile;

    public function handle(Request $request): Response
    {
        $lines = $this->tailLines(500);

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (preg_match('/\.(ERROR|CRITICAL|EMERGENCY|ALERT)\b/', $lines[$i]) === 1) {
                return $this->json([
                    'file' => $this->logFile(),
                    'error' => $lines[$i],
                ]);
            }
        }

        return $this->json([
            'file' => $this->logFile(),
            'error' => null,
            'message' => 'No error entries found in the log file.',
        ]);
    }
}

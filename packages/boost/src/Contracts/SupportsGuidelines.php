<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Contracts;

interface SupportsGuidelines
{
    /**
     * Absolute path to the agent's guideline file.
     */
    public function guidelinesPath(string $basePath): string;
}

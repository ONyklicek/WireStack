<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Contracts;

interface SupportsSkills
{
    /**
     * Absolute path to the directory where agent skills are installed.
     */
    public function skillsPath(string $basePath): string;
}

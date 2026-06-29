<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsSkills;

class Junie extends Agent implements SupportsGuidelines, SupportsSkills
{
    public function key(): string
    {
        return 'junie';
    }

    public function name(): string
    {
        return 'Junie';
    }

    public function guidelinesPath(string $basePath): string
    {
        return $basePath.'/.junie/guidelines.md';
    }

    public function skillsPath(string $basePath): string
    {
        return $basePath.'/.junie/skills';
    }
}

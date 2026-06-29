<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use Symfony\Component\Finder\Finder;

/**
 * Copies the shipped Agent Skill modules (each a directory containing a
 * SKILL.md) into an agent's skills directory.
 */
class SkillInstaller
{
    public function __construct(private string $source) {}

    public static function default(): self
    {
        return new self(dirname(__DIR__, 2).'/resources/boost/skills');
    }

    /**
     * Install every skill module into the target directory.
     *
     * @return array<int, string> the installed skill names
     */
    public function install(string $targetDirectory): array
    {
        if (! is_dir($this->source)) {
            return [];
        }

        $installed = [];

        foreach (Finder::create()->directories()->in($this->source)->depth(0)->sortByName() as $skill) {
            $name = $skill->getFilename();
            $destination = $targetDirectory.'/'.$name;

            if (! is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            foreach (Finder::create()->files()->in($skill->getPathname()) as $file) {
                $relative = $file->getRelativePathname();
                $target = $destination.'/'.$relative;
                $directory = dirname($target);

                if (! is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }

                copy($file->getPathname(), $target);
            }

            $installed[] = $name;
        }

        return $installed;
    }
}

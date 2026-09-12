<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install;

use NyonCode\WireBoost\Support\WirePackages;
use Symfony\Component\Finder\Finder;

/**
 * Copies the Agent Skill modules (each a directory containing a SKILL.md) into
 * an agent's skills directory — those shipped with the package, plus any the
 * project keeps in `.ai/skills`, which is the same extension point the
 * guidelines have in `.ai/guidelines`.
 *
 * A project directory of the same name wins, one file at a time: it is read
 * after the shipped one and overwrites what it names, so a project can replace
 * a single SKILL.md without restating the rest of the module.
 */
class SkillInstaller
{
    /**
     * @param  array<int, string>  $sources  Skill directories, least specific first.
     */
    public function __construct(private array $sources, private WirePackages $packages) {}

    public static function default(): self
    {
        return new self([
            dirname(__DIR__, 2).'/resources/boost/skills',
            base_path('.ai/skills'),
        ], app(WirePackages::class));
    }

    /**
     * Install every skill module whose package this application has into the
     * target directory.
     *
     * @return array<int, string> the installed skill names
     */
    public function install(string $targetDirectory): array
    {
        $sources = array_values(array_filter($this->sources, 'is_dir'));

        if ($sources === []) {
            return [];
        }

        $installed = [];

        foreach ($this->modules($sources) as $name => $directories) {
            if (! $this->packages->shipsResource($name)) {
                continue;
            }

            foreach ($directories as $directory) {
                $this->copy($directory, $targetDirectory.'/'.$name);
            }

            $installed[] = $name;
        }

        return $installed;
    }

    /**
     * Skill name => the directories that contribute to it, least specific first.
     *
     * Grouped explicitly rather than by handing every source to one Finder: two
     * directories of the same name sort equal there, and which of them is copied
     * last — the one that wins — then rests on iteration order rather than on the
     * order the caller asked for.
     *
     * @param  array<int, string>  $sources
     * @return array<string, array<int, string>>
     */
    private function modules(array $sources): array
    {
        $modules = [];

        foreach ($sources as $source) {
            foreach (Finder::create()->directories()->in($source)->depth(0)->sortByName() as $skill) {
                $modules[$skill->getFilename()][] = $skill->getPathname();
            }
        }

        ksort($modules);

        return $modules;
    }

    /**
     * Copy one skill module, creating what it needs on the way.
     */
    private function copy(string $from, string $to): void
    {
        $this->ensureDirectory($to);

        foreach (Finder::create()->files()->in($from) as $file) {
            $target = $to.'/'.$file->getRelativePathname();

            $this->ensureDirectory(dirname($target));

            copy($file->getPathname(), $target);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support;

use Composer\InstalledVersions;

/**
 * The wireStack package set, and which of it this application actually has.
 *
 * One owner, because three surfaces answer the same question and drifted apart
 * the moment they each kept their own list: `application-info` reported the
 * versions, the guideline composer decided which conventions to load, and the
 * skill installer decided which workflows to ship. A stack that grew from four
 * packages to fourteen left all three describing the 1.x stack — an agent was
 * told `wire-panels` was not installed while writing a `ListPage` against it.
 *
 * Names are short (`wire-table`), because that is what a guideline file, a skill
 * directory and a docs filter are all named after; `composerName()` is the one
 * place the `nyoncode/` vendor prefix is spelled.
 */
final class WirePackages
{
    /**
     * The stack and its optional layers, in dependency order.
     *
     * @var array<int, string>
     */
    private const STACK = [
        'wire-core',
        'wire-forms',
        'wire-table',
        'wire-sortable',
        'wire-panels',
        'wire-admin',
        'wire-suite',
        'wire-boost',
    ];

    /**
     * The ready-made areas. Each is installed on its own and none requires
     * another, so "modules" is never a single package to ask Composer about.
     *
     * @var array<int, string>
     */
    private const MODULES = [
        'wire-module-auth',
        'wire-module-users',
        'wire-module-settings',
        'wire-module-audit',
        'wire-module-notifications',
        'wire-module-media',
    ];

    /**
     * Packages outside the stack that decide what a wire surface actually does.
     *
     * Each of these is an `auto` switch somewhere: the users module looks for
     * the permission package before it shows roles or teams, and for Fortify or
     * `laravel/passkeys` before it shows the second factor. So "wire-module-users
     * is installed" does not answer whether the role screens exist, and an agent
     * that reads only the wire list writes against a surface that is switched
     * off. They are reported beside the stack for the same reason Livewire is.
     *
     * @var array<int, string>
     */
    private const COMPANIONS = [
        'livewire/livewire',
        'laravel/fortify',
        'laravel/passkeys',
        'nyoncode/laravel-permission-extended',
    ];

    /**
     * The name the docs corpus, the guidelines and the skills use for the six
     * modules at once. It is a grouping, not a composer package.
     */
    public const MODULES_GROUP = 'wire-modules';

    public const VENDOR = 'nyoncode';

    /**
     * Skill directories are named for the work, guideline files for the package.
     */
    private const SKILL_SUFFIX = '-development';

    /**
     * @param  array<string, string>  $versions  Composer name => pretty version.
     * @param  array<string, string>  $companions  The same, for {@see COMPANIONS}.
     */
    public function __construct(private array $versions, private array $companions = []) {}

    /**
     * The wire packages Composer reports as installed, in declared order.
     */
    public static function detect(): self
    {
        return new self(
            self::versionsOf(array_map(self::composerName(...), self::all())),
            self::versionsOf(self::COMPANIONS),
        );
    }

    /**
     * The installed subset of the given composer names, with their versions.
     *
     * @param  array<int, string>  $names
     * @return array<string, string>
     */
    private static function versionsOf(array $names): array
    {
        $versions = [];

        foreach ($names as $name) {
            if (InstalledVersions::isInstalled($name)) {
                $versions[$name] = (string) InstalledVersions::getPrettyVersion($name);
            }
        }

        return $versions;
    }

    /**
     * Every wire package name, the stack first and the modules after it.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [...self::STACK, ...self::MODULES];
    }

    /**
     * @return array<int, string>
     */
    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function composerName(string $name): string
    {
        return self::VENDOR.'/'.$name;
    }

    /**
     * Installed wire packages as composer name => version.
     *
     * @return array<string, string>
     */
    public function versions(): array
    {
        return $this->versions;
    }

    /**
     * Installed companion packages as composer name => version.
     *
     * @return array<string, string>
     */
    public function companions(): array
    {
        return $this->companions;
    }

    /**
     * Is this package present? {@see MODULES_GROUP} asks about the six modules
     * at once and is true as soon as any one of them is installed — the name
     * stands for a grouping, so requiring all six would hide the guideline from
     * every application that installed exactly the area it needed.
     */
    public function isInstalled(string $name): bool
    {
        if ($name === self::MODULES_GROUP) {
            foreach (self::MODULES as $module) {
                if (isset($this->versions[self::composerName($module)])) {
                    return true;
                }
            }

            return false;
        }

        return isset($this->versions[self::composerName($name)]);
    }

    /**
     * Should a guideline file or a skill directory named `$resource` be shipped
     * into an agent?
     *
     * Named after its package (`wire-panels.blade.php`, `wire-panels-development/`)
     * it ships only where that package is installed — telling an agent about
     * `ListPage` in an application that has no `wire-panels` is how a session
     * ends in a class that does not exist. Anything else — `core`, a one-off
     * like `wire-v2-upgrade`, a project's own file under `.ai/guidelines` — is
     * not this class's to judge and always ships.
     */
    public function shipsResource(string $resource): bool
    {
        $name = str_ends_with($resource, self::SKILL_SUFFIX)
            ? substr($resource, 0, -strlen(self::SKILL_SUFFIX))
            : $resource;

        if ($name !== self::MODULES_GROUP && ! in_array($name, self::all(), true)) {
            return true;
        }

        return $this->isInstalled($name);
    }
}

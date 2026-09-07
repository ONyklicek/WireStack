<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Support;

use Illuminate\Container\Container;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleSettings\Contracts\DescribesSettingsGroup;
use NyonCode\WireModuleSettings\Contracts\SettingsGroup;

/**
 * Where a *package* contributes a settings tab.
 *
 * `wire-module-settings.groups` is the application's list, and it stayed the
 * application's: a panel's owner says what their panel configures. What it could
 * not express is the other half — a package that ships a feature and the tab
 * that configures it, which until now had to end its README with "now add this
 * class to your config", the one instruction every other surface in this
 * framework had already stopped giving. A module registers itself; so does its
 * settings.
 *
 *   // In the contributing package's service provider
 *   SettingsRegistry::instance()->register(MailSettings::class);
 *
 * The same shape {@see PageChrome} uses, and
 * for the same reason: neither end can name the other, so a registry in the
 * middle is what lets a package reach a screen it does not own.
 *
 * ## Ordering, which is the part that bites
 *
 * Provider order in a Laravel application is composer's discovery order, and it
 * is not a contract — so a contributing package's provider may well run *before*
 * this module's. Resolving an unbound concrete class hands the caller a fresh
 * instance every time, which would mean a registration written into an object
 * nothing else ever sees: the tab simply would not appear, on some machines,
 * depending on a lockfile. {@see instance()} binds on first touch, so whichever
 * provider gets there first creates the one instance and the other finds it.
 *
 * Where a group *sits* is not decided here. That is `sort()` on
 * {@see DescribesSettingsGroup}, which
 * the group already owns — so unlike `PageChrome`, this needs no sort argument
 * to make two contributors agree.
 */
final class SettingsRegistry
{
    /** @var array<int, class-string<SettingsGroup>> */
    private array $groups = [];

    /**
     * The registry for this application, bound on first touch.
     *
     * Deliberately not a static property holding the groups themselves: under
     * Octane a static outlives the request, and a settings tab registered by one
     * request would then be shown to every later one — including after the
     * package was removed. The container is per application instance, which is
     * the lifetime this actually has.
     */
    public static function instance(): self
    {
        $container = Container::getInstance();

        // `singletonIf`, not `singleton`: this module's own provider binds it
        // too, and whichever of the two runs second must not replace an instance
        // the first one has already been registered into.
        $container->singletonIf(self::class);

        /** @var self $registry */
        $registry = $container->make(self::class);

        return $registry;
    }

    /**
     * Contribute one or more groups.
     *
     * Idempotent by class, because a provider can boot twice — a package
     * required by two others, a test that boots the application again — and two
     * copies of one group is a duplicate tab whose second copy points at the
     * same storage.
     *
     * @param  class-string<SettingsGroup>  ...$classes
     */
    public function register(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (! in_array($class, $this->groups, true) && is_subclass_of($class, SettingsGroup::class)) {
                $this->groups[] = $class;
            }
        }
    }

    /**
     * Every contributed group, in the order it was registered.
     *
     * @return array<int, class-string<SettingsGroup>>
     */
    public function all(): array
    {
        return $this->groups;
    }

    /** Whether anything has contributed this storage group. */
    public function has(string $group): bool
    {
        foreach ($this->groups as $class) {
            if ($class::group() === $group) {
                return true;
            }
        }

        return false;
    }
}

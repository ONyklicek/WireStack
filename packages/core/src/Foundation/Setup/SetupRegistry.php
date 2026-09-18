<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

use Illuminate\Container\Container;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;

/**
 * Where a *package* contributes something the application still has to do.
 *
 *   // In the contributing package's service provider
 *   SetupRegistry::instance()->register(CreateFirstAdministrator::class);
 *
 * The same shape `wire-module-settings`' own `SettingsRegistry` uses — named in
 * prose rather than linked, because a `{@see}` here becomes an import and
 * wire-core may not name a module — and for the same reason: neither end can
 * name the other. `wire-suite`
 * must not learn what a media disk is, and `wire-module-media` must not require
 * the installer that will ask about it — so a registry in the middle is what
 * lets a package reach a surface it does not own.
 *
 * ## Ordering, which is the part that bites
 *
 * Provider order in a Laravel application is composer's discovery order, and it
 * is not a contract — so a contributing package's provider may well run before
 * `wire-core`'s. Resolving an unbound concrete class hands the caller a fresh
 * instance every time, which would mean a registration written into an object
 * nothing else ever sees: the step simply would not appear, on some machines,
 * depending on a lockfile. {@see instance()} binds on first touch, so whichever
 * provider gets there first creates the one instance and the other finds it.
 *
 * Where a step *sits* is not decided here — that is `sort()` on the step, which
 * it already owns, because order is correctness: creating the first
 * administrator before the tables exist is a step that cannot work.
 *
 * ## Classes, not instances
 *
 * A step is resolved from the container when the installer is about to ask it
 * anything, so it may take whatever it needs in a constructor and so that
 * nothing is built during boot for a command almost nobody runs.
 */
final class SetupRegistry
{
    /** @var array<int, class-string<SetupStep>> */
    private array $steps = [];

    /**
     * The registry for this application, bound on first touch.
     *
     * Deliberately not a static property holding the steps themselves: under
     * Octane a static outlives the request, and a step registered by one
     * request would then be seen by every later one — including after the
     * package was removed. The container is per application instance, which is
     * the lifetime this actually has.
     */
    public static function instance(): self
    {
        $container = Container::getInstance();

        // `singletonIf`, not `singleton`: every caller comes through here, so
        // the second provider to register a step must find the instance the
        // first one wrote into rather than replace it — and an application that
        // bound a registry of its own keeps it.
        $container->singletonIf(self::class);

        /** @var self $registry */
        $registry = $container->make(self::class);

        return $registry;
    }

    /**
     * Contribute one or more steps.
     *
     * Registering the same class twice is not an error and does not produce two
     * steps: a provider that boots in both a test and the application it is
     * testing is the ordinary case, not a mistake worth an exception.
     *
     * @param  class-string<SetupStep>  ...$steps
     */
    public function register(string ...$steps): static
    {
        foreach ($steps as $step) {
            if (! in_array($step, $this->steps, true)) {
                $this->steps[] = $step;
            }
        }

        return $this;
    }

    /**
     * Every contributed step class, in registration order.
     *
     * Registration order, not run order — the installer sorts, because `sort()`
     * lives on the step and reading it means resolving it.
     *
     * @return array<int, class-string<SetupStep>>
     */
    public function all(): array
    {
        return $this->steps;
    }

    /**
     * Forget everything registered.
     *
     * For a test that needs to be the only contributor. Nothing in the
     * framework calls it.
     */
    public function flush(): static
    {
        $this->steps = [];

        return $this;
    }
}

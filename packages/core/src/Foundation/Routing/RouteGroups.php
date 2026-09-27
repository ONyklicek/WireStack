<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Container\Container;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;

/**
 * Every package's route group, by key — the one list `Route::wire()` and the
 * framework's route file read (ADR 0041).
 *
 * Class names, resolved when a group is registered: a provider registering here
 * must not construct anything, because the application may still rebind what the
 * group needs.
 */
final class RouteGroups
{
    /** @var array<string, class-string<ProvidesRoutes>> */
    private array $groups = [];

    public static function instance(): self
    {
        $container = Container::getInstance();

        // `singletonIf`, as SetupRegistry does: every provider comes through
        // here, and the second must find what the first wrote into.
        $container->singletonIf(self::class);

        return $container->make(self::class);
    }

    /** @param  class-string<ProvidesRoutes>  ...$groups */
    public function register(string ...$groups): static
    {
        foreach ($groups as $group) {
            $this->groups[$group::key()] = $group;
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    public function get(string $key): ProvidesRoutes
    {
        if (! $this->has($key)) {
            throw RouteRegistrationException::unknownGroup($key, array_keys($this->groups));
        }

        return Container::getInstance()->make($this->groups[$key]);
    }

    /** @return array<string, class-string<ProvidesRoutes>> */
    public function all(): array
    {
        return $this->groups;
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;

/**
 * Which dashboards this application has.
 *
 * Shaped after {@see ResourceRegistry} on
 * purpose — class names in, two questions answered, no routing and no URL shell —
 * and separate from it for the reason the layers demand: a dashboard is
 * `Widgets/` (L2) and the resource registry is `Core/` (L1), so one registry
 * holding both would drag L2 into L1.
 *
 * What lets a menu list both anyway is {@see RegistrySource}: `Workspace` reads
 * the catalogue, not registries, so it lists a dashboard without importing one —
 * and since ADR 0026 the router and the search palette read the same catalogue,
 * so a dashboard is routable by the same helper a resource is.
 */
final class DashboardRegistry implements RegistrySource
{
    /** @var array<string, class-string<Dashboard>> Keyed by dashboard key. */
    private array $dashboards = [];

    /**
     * @param  class-string<Dashboard>  $dashboard
     *
     * @throws ResourceRegistrationException When the class is not a dashboard, or
     *                                       when its key is already taken by a different class.
     */
    public function register(string $dashboard): void
    {
        if (! is_subclass_of($dashboard, Dashboard::class)) {
            throw ResourceRegistrationException::notAResource($dashboard, Dashboard::class);
        }

        $key = $dashboard::key();
        $existing = $this->dashboards[$key] ?? null;

        // Registering the same class twice is idempotent — config merging and a
        // provider that boots twice in tests both do it. Two *different* classes
        // claiming one key is the real error, exactly as it is for resources.
        if ($existing !== null && $existing !== $dashboard) {
            throw ResourceRegistrationException::duplicateResourceKey($key, $existing, $dashboard);
        }

        $this->dashboards[$key] = $dashboard;
    }

    /**
     * Register everything a config entry lists, ignoring anything unusable.
     *
     * Same rule as the resource registry's: application config with a stray
     * value should not take the application down at boot.
     */
    public function registerMany(mixed $dashboards): void
    {
        if (! is_array($dashboards)) {
            return;
        }

        foreach ($dashboards as $dashboard) {
            if (is_string($dashboard) && $dashboard !== '') {
                $this->register($dashboard);
            }
        }
    }

    /** @return array<string, class-string<Dashboard>> */
    public function all(): array
    {
        return $this->dashboards;
    }

    /** @return class-string<Dashboard>|null */
    public function find(string $key): ?string
    {
        return $this->dashboards[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->dashboards[$key]);
    }

    /**
     * The dashboards this source offers — every registered one. Which of them
     * are listed or routed is decided by `ProvidesNavigation` and
     * `ProvidesPages`, as it is for resources.
     *
     * @return array<string, class-string>
     */
    public function registeredClasses(): array
    {
        return $this->all();
    }
}

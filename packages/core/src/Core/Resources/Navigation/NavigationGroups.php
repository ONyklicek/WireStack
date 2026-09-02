<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use NyonCode\WireCore\Core\Resources\ResourceRegistry;

/**
 * The navigation groups an application has declared.
 *
 * Deliberately tiny, and deliberately separate from
 * {@see ResourceRegistry}: a resource declares
 * which group it belongs to, but no resource owns the group — the heading, its
 * icon and its position belong to whoever composes that part of the application.
 * Today that is a service provider; from V2.6 step 5 it is the domain module
 * that ships those resources together.
 *
 * A group nothing declares still works. `Workspace` makes an implicit one from
 * the key, so `->group('billing')` on a single resource needs no registration at
 * all, and registering exists to say the things a bare key cannot: a translated
 * heading, an icon, an order, a visibility rule written once instead of on every
 * resource.
 *
 * Registering the same key twice **replaces**, and that is the point rather than
 * an oversight: a module ships a group, and the application that installs it
 * adjusts the order or the label without editing the module. Unlike a resource
 * key — where two classes claiming one key silently take over each other's
 * routing — both registrations here mean the same group and nothing routes on it.
 */
final class NavigationGroups
{
    /** @var array<string, NavigationGroup> Keyed by group key, in registration order. */
    private array $groups = [];

    public function register(NavigationGroup $group): void
    {
        $this->groups[$group->getKey()] = $group;
    }

    /**
     * Register everything an iterable holds, ignoring anything that is not a group.
     *
     * The filtering is here rather than in the caller for the reason
     * `ResourceRegistry::registerMany()` gives: it is a rule about what the
     * registry accepts, not about how a provider boots.
     */
    public function registerMany(mixed $groups): void
    {
        if (! is_iterable($groups)) {
            return;
        }

        foreach ($groups as $group) {
            if ($group instanceof NavigationGroup) {
                $this->register($group);
            }
        }
    }

    public function find(string $key): ?NavigationGroup
    {
        return $this->groups[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->groups[$key]);
    }

    /** @return array<string, NavigationGroup> */
    public function all(): array
    {
        return $this->groups;
    }
}

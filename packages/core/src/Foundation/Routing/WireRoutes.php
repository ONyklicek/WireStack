<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;

/**
 * The one way a package's routes reach the router, from either side (ADR 0041).
 *
 * **From the application's route file**: `Route::wire('tenants')` inside any
 * group Laravel can build — the group is Laravel's, so everything it supports
 * applies, and the routes come back keyed for anything one of them needs alone.
 *
 * **From config**: `wire-core.routes.groups` describes the groups instead, and
 * the framework's route file registers them — each entry takes Laravel's group
 * attributes, the group's own options and changes to single routes.
 *
 * Both end in {@see wire()}, so a group means the same whichever side placed it,
 * and a route both sides register is refused rather than silently replaced.
 */
final class WireRoutes
{
    public const MACRO = 'wire';

    /**
     * Config keys that are Laravel group attributes, and the attribute each is.
     * Everything else in an entry is the group's own option.
     */
    private const ATTRIBUTES = [
        'prefix' => 'prefix',
        'domain' => 'domain',
        'middleware' => 'middleware',
        'without_middleware' => 'excluded_middleware',
        'as' => 'as',
        'where' => 'where',
        'namespace' => 'namespace',
        'scope_bindings' => 'scope_bindings',
    ];

    /** Keys of an entry that are neither an attribute nor an option. */
    private const RESERVED = ['uses', 'enabled', 'can', 'routes'];

    /**
     * Register a group's routes inside the group being built.
     *
     * `$options['routes']` changes single routes, by the key the group hands
     * them back under: `['register' => ['middleware' => [...], 'can' => '…']]`.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, Route>
     */
    public function wire(string $key, array $options = []): array
    {
        $group = RouteGroups::instance()->get($key);

        if ($group->fixesNames()) {
            FixedRouteNames::refuseNamedGroup(self::MACRO."('{$key}')");
        }

        $changes = (array) ($options['routes'] ?? []);
        unset($options['routes']);

        $before = $this->names();
        $routes = $group->register($options);

        // The same page registered over itself — same name, same address —
        // changes nothing anybody can see, and is allowed. The same name at
        // another address would quietly take the first one's links.
        foreach ($routes as $route) {
            $name = $route->getName();

            if ($name !== null && isset($before[$name]) && $before[$name] !== $this->address($route)) {
                throw RouteRegistrationException::registeredTwice($key, $name);
            }
        }

        foreach ($changes as $routeKey => $change) {
            if (isset($routes[$routeKey]) && is_array($change)) {
                $this->change($routes[$routeKey], $change);
            }
        }

        return $routes;
    }

    /**
     * Register every group `wire-core.routes.groups` describes — what the
     * framework's route file does, and nothing else does.
     *
     * An entry's key names the group it registers unless `uses` names another;
     * then the entry's key is its zone, so `'admin' => ['uses' => 'panel']` is
     * the panel's `admin` zone. `false`, or `'enabled' => false`, skips it.
     */
    public function registerConfigured(): void
    {
        foreach ((array) config('wire-core.routes.groups', []) as $name => $entry) {
            if ($entry === false || (is_array($entry) && ($entry['enabled'] ?? true) === false)) {
                continue;
            }

            $entry = is_array($entry) ? $entry : [];
            $key = (string) ($entry['uses'] ?? $name);
            $entry = $this->withDefaults($key, $entry);
            $options = array_diff_key($entry, self::ATTRIBUTES, array_flip(self::RESERVED));

            if ($key !== $name) {
                $options['zone'] ??= (string) $name;
            }

            $options['routes'] = $entry['routes'] ?? [];

            RouteFacade::group($this->attributes($entry), function () use ($key, $options): void {
                $this->wire($key, $options);
            });
        }
    }

    /**
     * The config entries that register this group — for an installer or a
     * generator asking whether, and where, it is routed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function configured(string $key): array
    {
        $entries = [];

        foreach ((array) config('wire-core.routes.groups', []) as $name => $entry) {
            if (! is_array($entry) || ($entry['enabled'] ?? true) === false) {
                continue;
            }

            if ((string) ($entry['uses'] ?? $name) === $key && RouteGroups::instance()->has($key)) {
                $entries[(string) $name] = $this->withDefaults($key, $entry);
            }
        }

        return $entries;
    }

    /**
     * An entry over the application's shared `wire-core.routes.defaults` over
     * what the group itself starts from — so a guard the package ships stays
     * unless the application replaces it on purpose.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function withDefaults(string $key, array $entry): array
    {
        return [
            ...RouteGroups::instance()->get($key)->defaults(),
            ...(array) config('wire-core.routes.defaults', []),
            ...$entry,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function attributes(array $entry): array
    {
        $attributes = [];

        foreach (self::ATTRIBUTES as $key => $attribute) {
            if (array_key_exists($key, $entry) && $entry[$key] !== null) {
                $attributes[$attribute] = $entry[$key];
            }
        }

        if (isset($entry['can'])) {
            $attributes['middleware'] = [...(array) ($attributes['middleware'] ?? []), 'can:'.$entry['can']];
        }

        return $attributes;
    }

    /** @param  array<string, mixed>  $change */
    private function change(Route $route, array $change): void
    {
        if (isset($change['middleware'])) {
            $route->middleware((array) $change['middleware']);
        }

        if (isset($change['can'])) {
            $route->middleware('can:'.$change['can']);
        }

        if (isset($change['without_middleware'])) {
            $route->withoutMiddleware((array) $change['without_middleware']);
        }

        if (isset($change['where'])) {
            $route->where((array) $change['where']);
        }
    }

    /** @return array<string, string> Every named route's address, by name. */
    private function names(): array
    {
        $names = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (($name = $route->getName()) !== null) {
                $names[$name] = $this->address($route);
            }
        }

        return $names;
    }

    private function address(Route $route): string
    {
        return implode('|', $route->methods()).' '.$route->getDomain().'/'.$route->uri();
    }
}

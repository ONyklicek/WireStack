<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Registration;

use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;

/**
 * Everything this application registered, whatever kind it is.
 *
 * One list read by three surfaces — the menu, the router and the global search
 * palette. Before ADR 0026 only the menu asked a seam; the other two held a
 * `ResourceRegistry` each, so a dashboard could appear in a menu and never be
 * routed or found, and a domain module that declared both got one of the three.
 *
 * Deliberately smaller than `Workspace`: it groups nothing, sorts nothing,
 * renders nothing and holds no URL. It answers one question — which keys exist
 * and what class each stands for — and owns the rule that makes the answer
 * usable, which is that a key means one thing.
 *
 * In `Foundation/` (L0) because all three of its readers must see it and they do
 * not live together: `ResourceRegistry` is L1, `DashboardRegistry` is L2, and
 * `ResourceRoutes` is in another package entirely (ADR 0025's layer map).
 */
final class Catalog
{
    /**
     * @param  array<int, RegistrySource>  $sources  Read in order, which decides registration order.
     */
    public function __construct(private readonly array $sources) {}

    /**
     * Every registered class, keyed, in registration order.
     *
     * @return array<string, class-string>
     *
     * @throws ResourceRegistrationException When two sources claim one key.
     */
    public function all(): array
    {
        // Not memoized, deliberately. A registry is filled during boot and a
        // test fills one mid-run, so a catalogue that cached its first read
        // would answer with whatever existed at the moment something happened
        // to ask first — which is a bug that only appears when the order of two
        // unrelated things changes. The loop is a handful of arrays.
        $classes = [];

        foreach ($this->sources as $source) {
            foreach ($source->registeredClasses() as $key => $class) {
                $existing = $classes[$key] ?? null;

                // Refused rather than resolved. Whichever way it were resolved,
                // one registration would take another's place — silently in a
                // menu, and at a URL prefix in the router.
                //
                // This used to live in `Workspace::registered()`, where it only
                // ran if something rendered a menu: an application that routes
                // and searches but draws its own navigation was the one it could
                // not protect. Here it runs on the first read, and all three
                // surfaces read.
                if ($existing !== null && $existing !== $class) {
                    throw ResourceRegistrationException::duplicateResourceKey($key, $existing, $class);
                }

                $classes[$key] = $class;
            }
        }

        return $classes;
    }

    /**
     * Every registered class implementing one capability, keyed.
     *
     * The shape all three consumers need, written once: registered is not
     * listed, routed or searchable, so each filters {@see all()} by its own
     * opt-in contract and none of them should be writing that loop.
     *
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $capability
     * @return array<string, class-string<TCapability>>
     */
    public function implementing(string $capability): array
    {
        return array_filter(
            $this->all(),
            static fn (string $class): bool => is_subclass_of($class, $capability),
        );
    }

    /** @return class-string|null */
    public function find(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use NyonCode\WireCore\Foundation\View\PageChrome;

/**
 * Every tour an application has registered.
 *
 * Bound as a singleton and written to from a service provider's boot, the way
 * {@see PageChrome} is — and idempotent by id for the same reason it is
 * idempotent by view name: a provider can run twice (a package required by two
 * others, a test that boots the application again), and two copies of one tour
 * would be two entries competing for one acknowledgement.
 *
 * **Later registrations of an id are ignored, not merged.** A second
 * `Tour::make('getting-started')` is far more likely to be the same provider
 * booting again than an intentional redefinition, and silently merging two
 * definitions would produce a tour neither author wrote.
 *
 * Nothing is registered by default. The framework ships no tour of its own — see
 * `architecture/plans/first-run-tours.md` — so an application that registers
 * none has an empty registry and pays nothing for the feature.
 */
final class Tours
{
    /** @var array<string, Tour> */
    private array $tours = [];

    /**
     * Register one or more tours, ignoring an id already present.
     *
     * Each is checked with {@see Tour::assertUsable()} as it arrives: this is
     * the first moment a definition is finished, and a tour that cannot work is
     * worth a failure at boot rather than a screen that quietly shows nothing.
     */
    public function register(Tour ...$tours): void
    {
        foreach ($tours as $tour) {
            if (isset($this->tours[$tour->getId()])) {
                continue;
            }

            $tour->assertUsable();

            $this->tours[$tour->getId()] = $tour;
        }
    }

    /**
     * Every registered tour, lowest {@see Tour::sort()} first.
     *
     * Sorted here rather than by the caller so there is one answer to "which of
     * these wins", and stable within a sort: `usort` has been stable since PHP
     * 8.0, so tours that do not order themselves keep the order they were
     * registered in, and an application with one tour per screen never has to
     * think about sorting at all.
     *
     * @return array<int, Tour>
     */
    public function all(): array
    {
        $tours = array_values($this->tours);

        usort($tours, static fn (Tour $a, Tour $b): int => $a->getSort() <=> $b->getSort());

        return $tours;
    }

    public function get(string $id): ?Tour
    {
        return $this->tours[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->tours[$id]);
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\NullPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;

/**
 * What one person pinned in the menu, and where they have been lately.
 *
 * Stored through the {@see PreferenceDriver} every other per-user surface uses —
 * a table's columns, a dashboard's layout — never a store of its own: the bag is
 * `['pinned' => [...keys], 'recent' => [...keys]]` under the surface
 * `navigation`, one bag **per zone** (`navigation:business`), because what is
 * pinned in one mount point does not belong in another.
 *
 * **Only keys are stored, and they are never drawn from storage.** A pinned key
 * whose resource was uninstalled, hidden from this person, or is not routed in
 * this zone would otherwise be a row pointing nowhere. So the readers take the
 * menu `Workspace` just built and return the entries of it that were stored —
 * the intersection, in the stored order.
 *
 * **Nothing is offered when nothing is kept.** The default driver is the null
 * one, and a pin in an application that stores nothing would be a button that
 * silently does nothing. {@see stores()} is the question a surface asks before
 * drawing a pin at all.
 */
final class NavigationMemory
{
    /** How many recent pages are kept, and drawn. */
    public const RECENT = 5;

    public function __construct(private readonly ?PreferenceDriver $driver = null) {}

    /** Whether anything pinned here would still be pinned on the next page. */
    public function stores(): bool
    {
        return ! $this->driver() instanceof NullPreferenceDriver;
    }

    /** @return array<int, string> */
    public function pinned(?string $zone = null): array
    {
        return $this->list('pinned', $zone);
    }

    public function isPinned(string $key, ?string $zone = null): bool
    {
        return in_array($key, $this->pinned($zone), true);
    }

    public function pin(string $key, ?string $zone = null): void
    {
        if (! $this->isPinned($key, $zone)) {
            $this->write('pinned', [...$this->pinned($zone), $key], $zone);
        }
    }

    public function unpin(string $key, ?string $zone = null): void
    {
        $this->write('pinned', array_values(array_diff($this->pinned($zone), [$key])), $zone);
    }

    /**
     * The recently visited keys, the most recent first.
     *
     * @return array<int, string>
     */
    public function recent(?string $zone = null): array
    {
        return $this->list('recent', $zone);
    }

    /**
     * Put a key at the front of the recent list — called when its page mounts,
     * never while a menu renders: a GET that draws the sidebar must not write.
     */
    public function remember(string $key, ?string $zone = null): void
    {
        $recent = array_values(array_diff($this->recent($zone), [$key]));

        array_unshift($recent, $key);

        // A few more than are drawn, so a pinned or the current key taking a
        // place in the list still leaves a full row of others.
        $this->write('recent', array_slice($recent, 0, self::RECENT * 2), $zone);
    }

    /**
     * The pinned entries of the menu this person is looking at, in pinned order.
     *
     * @param  array<string, NavigationItem>  $items  {@see Workspace::items()} for the same zone.
     * @return array<string, NavigationItem>
     */
    public function pinnedEntries(array $items, ?string $zone = null): array
    {
        return $this->pick($items, $this->pinned($zone));
    }

    /**
     * The recent entries, without the page being shown and without the pinned
     * ones — a row that leads where you already are, or repeats the row above
     * it, is a row that says nothing.
     *
     * @param  array<string, NavigationItem>  $items  {@see Workspace::items()} for the same zone.
     * @return array<string, NavigationItem>
     */
    public function recentEntries(array $items, ?string $zone = null, ?string $current = null): array
    {
        $keys = array_diff($this->recent($zone), $this->pinned($zone), [$current]);

        return array_slice($this->pick($items, array_values($keys)), 0, self::RECENT, true);
    }

    /**
     * @param  array<string, NavigationItem>  $items
     * @param  array<int, string>  $keys
     * @return array<string, NavigationItem>
     */
    private function pick(array $items, array $keys): array
    {
        $picked = [];

        foreach ($keys as $key) {
            if (isset($items[$key])) {
                $picked[$key] = $items[$key];
            }
        }

        return $picked;
    }

    /** @return array<int, string> */
    private function list(string $name, ?string $zone): array
    {
        $bag = $this->driver()->load($this->surface($zone), $this->user());
        $keys = $bag[$name] ?? [];

        return is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
    }

    /** @param  array<int, string>  $keys */
    private function write(string $name, array $keys, ?string $zone): void
    {
        $surface = $this->surface($zone);
        $user = $this->user();

        // The whole bag, not just this list: a driver replaces what it stores,
        // and writing `recent` alone would drop every pin.
        $bag = $this->driver()->load($surface, $user);
        $bag[$name] = $keys;

        $this->driver()->save($surface, $user, $bag);
    }

    private function surface(?string $zone): string
    {
        return $zone === null || $zone === '' ? 'navigation' : 'navigation:'.$zone;
    }

    private function user(): ?Authenticatable
    {
        return Auth::user();
    }

    private function driver(): PreferenceDriver
    {
        return PreferenceManager::resolve($this->driver, $this->user() !== null);
    }
}

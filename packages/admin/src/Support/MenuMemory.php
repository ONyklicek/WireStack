<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Support;

use NyonCode\WireCore\Core\Resources\Navigation\NavigationMemory;

/**
 * Whether the menu offers pins and recent pages — asked in one place.
 *
 * Two answers, each the application's switch AND something to keep it in:
 * `wire-admin.navigation.pins` / `.recent` say whether the menu wants it, and
 * {@see NavigationMemory::stores()} whether anything would remember it. A pin
 * over the `null` driver would be a button that silently does nothing, so the
 * second half is not optional.
 *
 * The sidebar (draws the pins and mounts the section), the section itself
 * (draws and writes) and the `page.mounting` hook (records a visit) all ask
 * here, so turning one off turns it off everywhere — including the write: an
 * application that hides "Recent" stores no visits either.
 */
final class MenuMemory
{
    public function __construct(private readonly NavigationMemory $memory) {}

    public static function make(): self
    {
        return new self(app(NavigationMemory::class));
    }

    /** Rows carry a pin and the "Pinned" section is drawn. */
    public function pins(): bool
    {
        return (bool) config('wire-admin.navigation.pins', true) && $this->memory->stores();
    }

    /** Visited pages are recorded and the "Recent" section is drawn. */
    public function recent(): bool
    {
        return (bool) config('wire-admin.navigation.recent', true) && $this->memory->stores();
    }

    /** Whether the section above the groups has anything to show at all. */
    public function any(): bool
    {
        return $this->pins() || $this->recent();
    }

    public function memory(): NavigationMemory
    {
        return $this->memory;
    }
}

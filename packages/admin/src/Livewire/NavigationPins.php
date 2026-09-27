<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationMemory;
use NyonCode\WireCore\Core\Resources\Workspace;

/**
 * The menu's "Pinned" and "Recent" section — the one part of the menu that
 * changes without a page load, and so the one part that is a Livewire component.
 *
 * The rest of the sidebar stays a Blade component. A pin on a row is a plain
 * button that dispatches `wire-admin-pin` in the browser; this listens, writes
 * through {@see NavigationMemory} and redraws itself, and tells the rows which
 * keys are pinned now (`wire-admin-pinned`) so each can show its own state. A
 * pinned entry here is a **copy** of its row, named apart
 * (`admin-nav-pinned-item`) — the row stays in its group.
 *
 * Drawn only where something is kept: the sidebar asks
 * {@see NavigationMemory::stores()} before mounting this at all.
 */
class NavigationPins extends Component
{
    /** The zone the menu was drawn for — read by the sidebar on the page render, and carried. */
    public ?string $zone = null;

    /** The registered key of the page being shown, left out of "Recent". */
    public ?string $current = null;

    public bool $linkedOnly = false;

    public function toggle(string $key): void
    {
        $memory = app(NavigationMemory::class);

        $memory->isPinned($key, $this->zone)
            ? $memory->unpin($key, $this->zone)
            : $memory->pin($key, $this->zone);

        $this->dispatch('wire-admin-pinned', keys: $memory->pinned($this->zone));
    }

    public function render(): View
    {
        $memory = app(NavigationMemory::class);
        $items = app(Workspace::class)->items($this->zone, $this->linkedOnly);

        return view('wire-admin::livewire.navigation-pins', [
            'pinned' => $memory->pinnedEntries($items, $this->zone),
            'recent' => $memory->recentEntries($items, $this->zone, $this->current),
            // The page's own reading was taken when it rendered; on a round
            // trip the route is `livewire.update`, so only the key is carried.
            'active' => new ActiveNavigation(key: $this->current),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireAdmin\Support\MenuMemory;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
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
 * Drawn only where something is kept and the application wants it: the
 * sidebar asks {@see MenuMemory} before mounting this at all, and each half
 * (`wire-admin.navigation.pins`, `.recent`) is switched on its own.
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
        $settings = MenuMemory::make();
        $memory = $settings->memory();

        // Switched off, a forged `wire-admin-pin` writes nothing either.
        if (! $settings->pins()) {
            return;
        }

        // Only what the menu offers a pin on. The key arrives from the browser,
        // and a heading with no page — or a key the menu no longer has — would
        // be stored and never drawn.
        if (! isset($this->pinnable()[$key])) {
            return;
        }

        $memory->isPinned($key, $this->zone)
            ? $memory->unpin($key, $this->zone)
            : $memory->pin($key, $this->zone);

        $this->dispatch('wire-admin-pinned', keys: $memory->pinned($this->zone));
    }

    public function render(): View
    {
        $settings = MenuMemory::make();
        $memory = $settings->memory();
        $items = $this->pinnable();

        return view('wire-admin::livewire.navigation-pins', [
            'pinned' => $settings->pins() ? $memory->pinnedEntries($items, $this->zone) : [],
            'recent' => $settings->recent() ? $memory->recentEntries($items, $this->zone, $this->current) : [],
            // A recent entry offers to pin it only where pins are on.
            'canPin' => $settings->pins(),
            // The page's own reading was taken when it rendered; on a round
            // trip the route is `livewire.update`, so only the key is carried.
            'active' => new ActiveNavigation(key: $this->current),
        ]);
    }

    /**
     * Every row the sidebar offers a pin on, keyed as the pin names it.
     *
     * Read off the grouped menu, the one the sidebar draws, not the flat
     * `Workspace::items()`: a child adopted through `parent()` sits under its
     * parent only there, and a hand-written child is in neither list on its
     * own. A row is pinnable when it is a page — it has a URL — and a key to be
     * kept by. A row with children never is: it is the heading of its group,
     * a disclosure rather than a page, even when its resource has a URL, so its
     * pinned copy would be a link standing for a list. A key stored before
     * that held is not drawn. The first row under a key wins.
     *
     * @return array<string, NavigationItem>
     */
    private function pinnable(): array
    {
        $items = [];

        foreach (app(Workspace::class)->navigation($this->zone, $this->linkedOnly) as $group) {
            foreach ($group->getItems() as $key => $item) {
                if ($item->getUrl() !== null && ! $item->hasChildren()) {
                    $items[$key] ??= $item;
                }

                foreach ($item->getChildren() as $child) {
                    $childKey = $child->getKey();

                    if ($childKey !== null && $child->getUrl() !== null) {
                        $items[$childKey] ??= $child;
                    }
                }
            }
        }

        return $items;
    }
}

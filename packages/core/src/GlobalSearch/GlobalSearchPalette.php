<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;

/**
 * The command palette: one search box over every registered resource.
 *
 * A Livewire component for the reason the notification bell is one — every
 * keystroke is a round trip — and, like the bell, it composes no host trait:
 * there is no form to bind and no table to drive. Named in prose rather than
 * with a `{@see}`, because the tag needs an import and `Notifications` is
 * another L2 module: the layer test rejects the edge, and it is right to.
 *
 * It holds no query. {@see GlobalSearch} owns the search, the per-resource cap
 * and the authorization check, so the rule that decides what a user is allowed
 * to see has one owner rather than one per surface.
 *
 * Mount it once in the layout:
 *
 *   `@livewire`('wire-global-search')
 *
 * Opening it is the application's business — a button, or a ⌘K binding on the
 * markup below. The component does not bind a global shortcut itself, because a
 * framework that claimed ⌘K on every page would be taking a key the application
 * may already use.
 *
 * @property-read array<string, array<int, GlobalSearchResult>> $results The
 *   cached computed property behind {@see getResultsProperty()}. Declared so
 *   that reading it — which every caller in here does, rather than calling the
 *   method and paying for the whole search again — is a typed read instead of
 *   magic static analysis has to take on trust.
 */
class GlobalSearchPalette extends Component
{
    public bool $open = false;

    public string $term = '';

    /**
     * The active row, as a flat index over every group.
     *
     * Flat rather than (group, row) because that is what the arrow keys move
     * through: a user pressing Down at the end of one group expects the first
     * row of the next, not nothing.
     */
    public int $active = 0;

    /**
     * Opened by the application, on whatever it decides the shortcut is.
     *
     * A listener rather than a bound key: a framework that claimed ⌘K on every
     * page would be taking a combination the application may already use, so the
     * page dispatches `open-global-search` and this answers it.
     */
    #[On('open-global-search')]
    public function open(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->term = '';
        $this->active = 0;
    }

    /**
     * Reset the cursor whenever the term changes.
     *
     * Without this, typing one more character while sitting on row four keeps
     * the cursor on row four of a completely different result set — and Enter
     * then opens something the user never looked at.
     */
    public function updatedTerm(): void
    {
        $this->active = 0;
    }

    public function moveDown(): void
    {
        $count = count($this->flatResults());

        if ($count > 0) {
            $this->active = ($this->active + 1) % $count;
        }
    }

    public function moveUp(): void
    {
        $count = count($this->flatResults());

        if ($count > 0) {
            $this->active = ($this->active - 1 + $count) % $count;
        }
    }

    /**
     * Where the active row goes, or null when there is nowhere to go.
     *
     * Returned rather than redirected to, so a caller can decide: the palette
     * navigates by default, and an application that renders its results into a
     * panel instead needs the destination, not a response.
     */
    public function selectedUrl(): ?string
    {
        return ($this->flatResults()[$this->active] ?? null)?->url;
    }

    public function select(): mixed
    {
        $url = $this->selectedUrl();

        if ($url === null) {
            return null;
        }

        $this->close();

        return $this->redirect($url, navigate: true);
    }

    /**
     * @return array<string, array<int, GlobalSearchResult>>
     */
    public function getResultsProperty(): array
    {
        return $this->searcher()->search($this->term);
    }

    /**
     * The heading each group of results is shown under, keyed by resource key.
     *
     * The key is an identifier — a config key, a route segment, a `wire:key` —
     * and `pluralLabel()` is the plural human name; putting the first on screen
     * would be a second vocabulary for the second, and wrong the moment a
     * resource makes them differ on purpose (`orders` / `Sales Orders`).
     *
     * One static call per group, not per row, and only for the groups that
     * actually matched. A key with no resource behind it keeps the key, which is
     * what a registry emptied between the search and the render leaves.
     *
     * @return array<string, string>
     */
    public function groupLabels(): array
    {
        $registry = app(ResourceRegistry::class);

        $labels = [];

        foreach (array_keys($this->results) as $key) {
            $resource = $registry->find($key);

            $labels[$key] = $resource === null ? $key : $resource::pluralLabel();
        }

        return $labels;
    }

    /**
     * The same rows in one list, in the order they are rendered.
     *
     * The keyboard cursor is an index into this, so it and the markup have to
     * walk the groups the same way — which is why both read this method rather
     * than each flattening the groups themselves.
     *
     * Reads `$this->results`, never `getResultsProperty()`. The property is the
     * cached computed property; the method behind it caches nothing, so every
     * caller that reaches for the method pays for the whole search again — one
     * query per opted-in resource, per caller, per keystroke.
     *
     * @return array<int, GlobalSearchResult>
     */
    public function flatResults(): array
    {
        $groups = $this->results;

        return $groups === [] ? [] : array_merge(...array_values($groups));
    }

    public function render(): View
    {
        return view('wire-core::global-search.palette');
    }

    /**
     * Resolved rather than constructed, so an application can bind its own.
     *
     * {@see GlobalSearch::searchResource()} and {@see GlobalSearch::matchAny()}
     * are protected because a resource whose match needs a join, a full-text
     * index or a search service has to replace them. Building the searcher with
     * `new` made both unreachable from the only surface that renders them — the
     * override would have been written and then silently never run.
     */
    protected function searcher(): GlobalSearch
    {
        return app(GlobalSearch::class);
    }
}

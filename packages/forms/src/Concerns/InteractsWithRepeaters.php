<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Livewire action endpoints backing the Repeater field's add / remove / reorder
 * buttons (see resources/views/components/repeater.blade.php).
 *
 * Repeaters are rendered by any host that embeds a form — both standalone form
 * components ({@see WithForms}) and table action
 * modals. The mutation logic is identical, so it lives here as the single
 * canonical owner instead of being duplicated per host.
 *
 * Reads use data_get, which traverses both plain array properties and the
 * ArrayAccess StateContainer used by table action modals. Writes go through
 * writeRepeaterItems(), which delegates through any StateContainer encountered
 * on the path — data_set() alone cannot modify an overloaded ArrayAccess
 * element by reference.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithRepeaters
{
    public function addRepeaterItem(string $statePath): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            $items = [];
        }

        $items[] = [];

        $this->writeRepeaterItems($statePath, $items);
    }

    public function removeRepeaterItem(string $statePath, int $index): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            return;
        }

        unset($items[$index]);

        $this->writeRepeaterItems($statePath, array_values($items));
    }

    /**
     * @param  array<int, int>  $order
     */
    public function reorderRepeaterItems(string $statePath, array $order): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            return;
        }

        $reordered = [];
        foreach ($order as $oldIndex) {
            if (isset($items[$oldIndex])) {
                $reordered[] = $items[$oldIndex];
                unset($items[$oldIndex]);
            }
        }

        // Preserve any items the order array didn't mention (a partial/stale order
        // must reorder, never silently drop rows). They keep their relative order.
        foreach ($items as $leftover) {
            $reordered[] = $leftover;
        }

        $this->writeRepeaterItems($statePath, $reordered);
    }

    /**
     * Write the items array back to the host at the given dot-notation path.
     *
     * Delegates to the canonical {@see StateContainer::writeInto()}: when a
     * StateContainer sits on the path (e.g. the table's `tableState` bag) the
     * write is routed through its set(), because data_set() cannot write through
     * an overloaded ArrayAccess element by reference. Plain array properties fall
     * through to data_set().
     *
     * @param  array<int, mixed>  $items
     */
    private function writeRepeaterItems(string $statePath, array $items): void
    {
        StateContainer::writeInto($this, $statePath, $items);
    }
}

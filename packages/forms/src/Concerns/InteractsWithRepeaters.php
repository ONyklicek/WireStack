<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Livewire action endpoints backing the Repeater field's add / clone / remove /
 * move / reorder buttons (see resources/views/components/repeater.blade.php).
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

    /**
     * Append one Builder block: the same array append, carrying the chosen block
     * type so the item knows which schema edits it.
     */
    public function addBuilderItem(string $statePath, string $block): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items)) {
            $items = [];
        }

        $items[] = ['type' => $block, 'data' => []];

        $this->writeRepeaterItems($statePath, $items);
    }

    /**
     * Duplicate one row and put the copy directly below its original.
     *
     * `$keyName` is stripped from the copy rather than kept, because a
     * relationship repeater's rows carry the child's primary key and the save
     * handler matches on it: two rows holding the same key would both `fill()`
     * the same record, the second overwriting the first, and one of the two would
     * be gone on reload. Passed in rather than assumed, since the component owns
     * the name ({@see Repeater::itemKeyName()})
     * and this endpoint serves every repeater on the page.
     *
     * A non-array row is copied as-is: there is nothing to strip, and refusing it
     * would make the button silently do nothing on data the repeater does render.
     */
    public function cloneRepeaterItem(string $statePath, int $index, string $keyName = 'id'): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items) || ! array_key_exists($index, $items)) {
            return;
        }

        $copy = $items[$index];

        if (is_array($copy)) {
            unset($copy[$keyName]);
        }

        array_splice($items, $index + 1, 0, [$copy]);

        $this->writeRepeaterItems($statePath, $items);
    }

    /**
     * Move one row to another position — the keyboard's half of reordering.
     *
     * The up/down buttons a reorderable repeater renders call this, and they
     * exist because a drag handle is not operable without a pointer. Out-of-range
     * targets are clamped rather than rejected: the buttons are hidden at the
     * ends, but a re-render racing a click can still send `-1`, and a silently
     * clamped move is a no-op where a thrown error is a broken form.
     */
    public function moveRepeaterItem(string $statePath, int $from, int $to): void
    {
        $items = data_get($this, $statePath, []);
        if (! is_array($items) || ! array_key_exists($from, $items)) {
            return;
        }

        $items = array_values($items);
        $to = max(0, min($to, count($items) - 1));

        if ($to === $from) {
            return;
        }

        $moved = array_splice($items, $from, 1);
        array_splice($items, $to, 0, $moved);

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

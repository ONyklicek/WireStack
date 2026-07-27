<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Table;

/**
 * The table's side of the gesture layer: which desktop pointer/keyboard
 * gestures this table offers.
 *
 * Configuration only — {@see TableGestures} owns the vocabulary and the
 * defaults, this trait owns where a table keeps its copy and what each
 * capability means once the table's own shape is taken into account (a sweep
 * needs a selectable table, the keyboard layer is auto-on for a grid).
 *
 * @phpstan-require-extends Table
 */
trait HasGestures
{
    /** Resolved lazily from `config('wire-table.defaults.gestures')`. */
    protected ?TableGestures $gestures = null;

    /** Whether rows can be selected at all — the prerequisite of every selection gesture. */
    abstract public function isSelectable(): bool;

    /** Whether the keyboard drives this table row by row. */
    abstract public function usesGridSemantics(): bool;

    /**
     * Switch the desktop gesture layer — keyboard grid navigation, range
     * selection, the drag sweep, the right-click menu, the `?` help and the fill
     * handle — on, off, or capability by capability:
     *
     *   $table->gestures(false);
     *   $table->gestures(fn (TableGestures $g) => $g->keyboard()->dragSelect(false));
     *
     * The closure receives this table's gestures (seeded from the config
     * default) and configures them in place; its return value is ignored, so
     * both a fluent chain and a multi-line body work.
     *
     * @param  bool|Closure(TableGestures): mixed|TableGestures  $gestures
     */
    public function gestures(bool|Closure|TableGestures $gestures = true): static
    {
        if ($gestures instanceof TableGestures) {
            $this->gestures = $gestures;

            return $this;
        }

        if (is_bool($gestures)) {
            $this->gestures = $gestures ? TableGestures::all() : TableGestures::none();

            return $this;
        }

        $gestures($this->getGestures());

        return $this;
    }

    /**
     * This table's gesture permissions, seeded once from the project default.
     */
    public function getGestures(): TableGestures
    {
        /** @var bool|array<string, bool>|null $default */
        $default = config('wire-table.defaults.gestures', true);

        return $this->gestures ??= TableGestures::fromConfig($default);
    }

    /**
     * Whether the mouse may sweep down the checkbox column to select a block —
     * a selectable table with the gesture allowed.
     */
    public function usesDragSelect(): bool
    {
        return $this->getGestures()->allowsDragSelect() && $this->isSelectable();
    }

    /**
     * Whether Shift/mod clicks and Shift+arrows work a range. Same prerequisite:
     * there has to be a selection for a range to grow in.
     */
    public function usesRangeSelection(): bool
    {
        return $this->getGestures()->allowsRangeSelection() && $this->isSelectable();
    }

    /**
     * Whether `?` opens the shortcut help. It is read by the keyboard layer, so
     * it can never outlive it — {@see usesGridSemantics()}.
     */
    public function usesShortcutHelp(): bool
    {
        return $this->getGestures()->allowsShortcutHelp() && $this->usesGridSemantics();
    }

    /**
     * Whether rows carry the active-row marker: the row the keyboard, a range or
     * a sweep continues from has to be visible, and every one of those gestures
     * needs somewhere to grow from.
     *
     * A table left with nothing but a declared click action does not mark
     * anything — a click there opens the record and moves on; a highlighted row
     * left behind would be an application affordance on a page that asked for
     * none.
     */
    public function usesActiveRowMarker(): bool
    {
        return $this->usesGridSemantics()
            || $this->usesRangeSelection()
            || $this->usesDragSelect();
    }

    /**
     * The mouse half of the gesture layer, as the delegated controller consumes
     * it. Separate from the keyboard config: a table may sweep and range with
     * the keyboard layer switched off entirely.
     *
     * @return array{sweep: bool, ranges: bool}
     */
    public function getGestureConfig(): array
    {
        return [
            'sweep' => $this->usesDragSelect(),
            'ranges' => $this->usesRangeSelection(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Table;

/**
 * How a row answers to a pointer, a keyboard and a drag.
 *
 * Part of {@see TableRenderPlan}. The gesture layer is opt-in and its halves are
 * switchable independently, so each surface carries its own config rather than
 * one flag deciding everything: the mouse half (sweep, Shift/mod ranges), the
 * keyboard half (grid navigation), the whole-row click/dblclick bindings, and the
 * row context menu, which is a dedicated action list independent of the actions
 * column.
 *
 * Two details here are load-bearing and easy to lose.
 *
 * **The active-row marker only exists where something continues from the marked
 * row** — the keyboard, a range or a sweep. A table left with nothing but a click
 * binding runs the action and highlights nothing, so `$activeRowConfig` is null
 * rather than an unused config.
 *
 * **The shortcut-help event name is derived from the component id.** A page can
 * hold several tables and a bare window event would open every help modal at
 * once, so the name is scoped per component. It goes through a lowercase md5
 * because the listener lives in an ATTRIBUTE NAME (`x-on:{event}.window`) and the
 * DOM lowercases those — a mixed-case Livewire id would never match what the
 * controller dispatches. A table whose legend is empty gets no event and no modal
 * at all, which is why the name is computed from the legend rather than from the
 * `usesShortcutHelp()` flag alone.
 *
 * The controller learns that name through its keyboard config, so `help` is
 * folded into {@see $recordKeyboardConfig} here. The view used to write that key
 * back onto the array a dozen lines after building it, which meant the config was
 * briefly wrong and anything reading it in between would have missed the event.
 */
final class InteractionRenderPlan
{
    /**
     * @param  array<string, mixed>  $recordActionBindings  Whole-row click and
     *                                                      dblclick bindings, as
     *                                                      a name map.
     * @param  array<string, mixed>|null  $recordKeyboardConfig  Null unless
     *                                                           keyboard nav is
     *                                                           on; carries the
     *                                                           `help` event.
     * @param  array<string, mixed>  $gestureConfig  The mouse half.
     * @param  array<string, mixed>|null  $activeRowConfig  Null when nothing
     *                                                      continues from a row.
     */
    private function __construct(
        public readonly bool $rowContextMenuEnabled,
        public readonly array $recordActionBindings,
        public readonly bool $keyboardNav,
        public readonly ?string $tableRole,
        public readonly ?array $recordKeyboardConfig,
        public readonly bool $recordActionsRootEnabled,
        public readonly array $gestureConfig,
        public readonly ?array $activeRowConfig,
        public readonly ?TableShortcutLegend $shortcutLegend,
        public readonly ?string $shortcutHelpEvent,
    ) {}

    public static function resolve(Table $table, mixed $component): self
    {
        $legend = $table->usesShortcutHelp() ? $table->shortcutLegend() : null;

        $helpEvent = $legend !== null && ! $legend->isEmpty()
            ? 'wire-table-shortcut-help-'.substr(md5($component->getId()), 0, 12)
            : null;

        $keyboardNav = $table->keyboardNavEnabled();

        $keyboardConfig = null;

        if ($keyboardNav) {
            $keyboardConfig = $table->getRecordActionKeyboardConfig();
            $keyboardConfig['help'] = $helpEvent;
        }

        return new self(
            rowContextMenuEnabled: $table->hasRowContextMenu(),
            recordActionBindings: $table->getRecordActionBindings(),
            keyboardNav: $keyboardNav,
            tableRole: $table->getTableRole(),
            recordKeyboardConfig: $keyboardConfig,
            // Grid semantics (role/tabindex) now cover selectable tables too, but
            // mounting the controller there is a visible change that ships
            // separately — hence its own owner rather than a derived flag.
            recordActionsRootEnabled: $table->mountsRecordActionController(),
            gestureConfig: $table->getGestureConfig(),
            activeRowConfig: $table->usesActiveRowMarker() ? $table->getActiveRowConfig() : null,
            shortcutLegend: $legend,
            shortcutHelpEvent: $helpEvent,
        );
    }
}

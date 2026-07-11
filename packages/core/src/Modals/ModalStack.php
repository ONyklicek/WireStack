<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals;

/**
 * Canonical owner of the modal-stacking geometry.
 *
 * When action modals stack (a modal opened from within a modal), each level is
 * layered above the previous one. This class is the single source of truth for
 * the z-index scale and the safety cap, so the hosts, the modal-host views and
 * the suspended-shell partial all derive the same numbers instead of hard-coding
 * `50 + depth * 10` in Blade.
 */
final class ModalStack
{
    /**
     * z-index of the bottom-most modal layer. Matches the `z-50` Tailwind class
     * every modal surface already ships with, so a single (non-stacked) modal is
     * unchanged.
     */
    public const BASE_Z_INDEX = 50;

    /**
     * z-index increment per stacked level. The 10-unit gap leaves head-room for a
     * modal's own internals (sticky header/footer at `z-10`) between levels.
     */
    public const Z_INDEX_STEP = 10;

    /**
     * Safety cap on how deep modals may stack. Modals open on user interaction,
     * not on render, so this is a guard against pathological re-entrancy (a
     * callback that opens a modal in a loop) rather than a normal-use limit — no
     * real UI stacks anywhere near this deep.
     */
    public const MAX_DEPTH = 8;

    /**
     * The z-index for the modal sitting at the given stack depth
     * (0 = bottom-most). Negative depths clamp to the base layer.
     */
    public static function zIndexForDepth(int $depth): int
    {
        return self::BASE_Z_INDEX + max(0, $depth) * self::Z_INDEX_STEP;
    }
}

<?php

declare(strict_types=1);

/*
 * The framework's own glyphs — the ones it draws that Heroicons has no answer
 * for, and that more than one package needs.
 *
 * Addressed as `wire:name`, loaded on first use. They live in core rather than in
 * whichever package drew them first because that is the lowest layer that can own
 * them: the star is drawn by the `Rating` FIELD and by the read-only
 * `RatingColumn`, in two packages, and a rating must not look like two different
 * things depending on whether you can edit it.
 *
 * Why not Heroicons' own star, which exists: the field emits all three states of
 * every position and switches them with x-show, so twenty <svg> per field at the
 * default max. Heroicons' star is 336 B of path against this one's 104 (426
 * against 157 outlined), which measured 14 718 B per field against a 10 400
 * ceiling in FormFieldPayloadTest. The compact glyph is what both surfaces
 * converged on.
 *
 * 24x24, fill-based; an icon that needs stroking says so on its own path. Do not
 * add a Heroicon here — those are already available unprefixed and as `outline:`.
 * Glyphs only one package draws belong to that package (see wire-forms'
 * `Support\Icons\FormsIconSet` for the editor toolbars).
 */

return [
    'star' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
    'star-outline' => '<path fill="none" stroke="currentColor" stroke-width="1.5" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
];

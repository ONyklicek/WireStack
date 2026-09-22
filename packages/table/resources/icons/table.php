<?php

declare(strict_types=1);

/*
 * The glyphs wire-table draws that no other package needs.
 *
 * There is exactly one reason this set exists: the table is the only place in
 * the stack that draws a checkbox BY HAND. Every other checkbox — wire-forms'
 * `Checkbox` and `CheckboxList`, wire-core's checkbox entry — is a native
 * `<input type="checkbox">` whose mark is drawn by `@tailwindcss/forms`, a bold
 * two-pixel tick. The table cannot use a native input (the row box is a
 * `role="checkbox"` button, because Alpine owns the selection state and a range
 * sweep has to survive a shift-click that a native input would consume), so it
 * has to draw the mark itself — and it used to borrow Heroicons' solid `check`
 * for the job. That glyph is a filled silhouette about 1.2 px thick at 16 px and
 * it spans its whole 20x20 viewBox, so inside a 16 px bordered box it came out
 * as a hairline jammed against the corners. Next to a native checkbox on the
 * same page it read as a different control.
 *
 * So these are checkbox marks, not general-purpose ticks: 16x16, stroked, sized
 * and inset to sit inside a small box. `checkbox-check` is deliberately NOT an
 * alias for Heroicons' `check` — a caller that wants a tick in a button or a
 * badge still wants the solid one.
 *
 * `checkbox-check` carries a `stroke-dasharray` because the mark draws itself
 * in: the selection cell transitions `stroke-dashoffset` from 12 to 0 when a
 * row becomes selected. 12 is one unit MORE than the dash (the path measures
 * 10.64), so the hidden state is unambiguously off the end of the path rather
 * than sitting exactly on its start, where a round cap can leave a dot behind.
 * The offset is never declared here: an undeclared `stroke-dashoffset` computes
 * to 0, which is the fully drawn mark, so a build that never generated the
 * arbitrary utility the animation rides on degrades to a tick that simply
 * appears. Getting that fallback backwards would hide the mark permanently.
 *
 * Registered under the `table` prefix, so a caller asks for
 * `icon('table:checkbox-check')` and a consumer can swap the set — or one glyph
 * — the way any other icon set is swapped. Root attributes (fill/stroke/caps)
 * live on the set, not on each path; see `Support\Icons\TableIconSet`.
 *
 * Do not add a glyph here that another package also draws. That one belongs in
 * core's `Foundation\Icons\WireIconSet`, which is what "more than one package
 * needs it" means.
 */

return [
    'checkbox-check' => '<path stroke-dasharray="11" d="M4.5 8.5 7 11 11.5 5.5"/>',
    'checkbox-indeterminate' => '<path d="M4.5 8h7"/>',
];

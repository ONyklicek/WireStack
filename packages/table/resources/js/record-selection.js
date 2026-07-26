/*
 * Delegated selection state for a wire-table. The desktop checkboxes, the
 * header and card select-all toggles, the bulk bar, the mobile cards and the
 * keyboard gestures all share this one Alpine component, reached through
 * `[data-selection-root]` on the table wrapper.
 *
 * Selection has two shapes, and `mode` decides what `selected` MEANS:
 *   'keys' → `selected` is the selection.
 *   'all'  → everything the filter matches is selected and `selected` holds
 *            the EXCLUSIONS, so the same toggle works for both and no key
 *            list of the whole result set ever reaches the browser.
 *
 * Kept import-free on purpose: when the compiled bundle is missing, the
 * asset partial inlines this file verbatim as the fallback — a dangling
 * `wireRecordSelection` reference would otherwise kill Alpine for the whole
 * table wrapper (search, filters, bulk bar, pagination, modal hosts).
 */

document.addEventListener('alpine:init', () => {
    // A function, NOT an arrow: Alpine binds a data factory's `this` to the
    // magic context that carries $wire.
    window.Alpine.data('wireRecordSelection', function (config = {}) {
        const statePath = config.statePath || 'tableState.selection';
        const syncLive = !! config.syncLive;
        const commitDelay = config.commitDelay ?? 350;

        return {
            // The entangles MUST be created here in the returned literal.
            // Created in init() they would store the raw interceptor object —
            // $wire is injected into the object separately, so nothing throws,
            // the selection just silently stops syncing.
            selected: this.$wire.entangle(statePath + '.records'),
            mode: this.$wire.entangle(statePath + '.mode'),
            commitTimer: null,

            /* The range anchor. One-shot and invisible: Space, a checkbox
               click or a Shift+range sets it, a plain arrow move clears it.
               Owned here — not by the record-action controller — because the
               anchor is selection state: what a range means depends on what
               is selected, and every selection surface shares this component. */
            anchorKey: null,

            /* Snapshot of the selection OUTSIDE the block around the anchor,
               taken when the first Shift+range grows from it. Mandatory: the
               range writes base ∪ range, and a union is monotone — shrinking
               the range back could not be derived from the current selected. */
            baseSelection: null,

            /* Read from the DOM, not seeded into the component: the root is
               keyed (wire:key table-wrapper), so Livewire morphs it in place
               and a seeded value would keep the first render's count forever
               (filter 128k rows down to 7 and the bar would still say 128k). */
            get matching() { return Number(this.$root.dataset.matching || 0); },
            get pageKeys() { return JSON.parse(this.$root.dataset.pageKeys || '[]'); },
            get selectsAll() { return this.mode === 'all'; },
            get selectedCount() { return this.selectsAll ? Math.max(0, this.matching - this.selected.length) : this.selected.length; },
            get allSelected() { return this.pageKeys.length > 0 && this.pageKeys.every((k) => this.isSelected(k)); },
            get someSelected() { return this.selectedCount > 0 && ! this.allSelected; },

            isSelected(key) {
                return this.selectsAll ? ! this.selected.includes(key) : this.selected.includes(key);
            },

            toggle(key) {
                this.selected = this.selected.includes(key)
                    ? this.selected.filter((k) => k !== key)
                    : [...this.selected, key];
                this.queueCommit();
            },

            toggleAll() {
                /* In all mode the list holds EXCLUSIONS, so the keys-mode
                   arithmetic below must never touch it — it used to, and one
                   header click turned the selection into its own complement.
                   The page gesture edits the exclusions and never leaves all
                   mode (mirrors selectAllRecords / deselectPageRecords):
                   leaving it would shrink an all-matching selection to one
                   page, and the rest of it cannot be carried into keys mode
                   without materialising every matching key here. */
                if (this.selectsAll) {
                    this.selected = this.allSelected
                        ? [...new Set([...this.selected, ...this.pageKeys])]
                        : this.selected.filter((k) => ! this.pageKeys.includes(k));
                    this.queueCommit();

                    return;
                }

                /* Selecting a page unions with other pages instead of
                   replacing them, which is what silently discarded a
                   selection when the user paged. */
                this.selected = this.allSelected
                    ? this.selected.filter((k) => ! this.pageKeys.includes(k))
                    : [...new Set([...this.selected, ...this.pageKeys])];
                this.queueCommit();
            },

            selectAllMatching() {
                this.clearAnchor();
                this.mode = 'all';
                this.selected = [];
                this.$wire.$commit();
            },

            selectOnlyPage() {
                this.clearAnchor();
                this.mode = 'keys';
                this.selected = [...this.pageKeys];
                this.$wire.$commit();
            },

            deselectAll() {
                // Explicit, not left to the mode watcher: deselecting from keys
                // mode changes no mode, and a stale anchor/base would hand the
                // next Shift+range a resurrected selection.
                this.clearAnchor();
                this.mode = 'keys';
                this.selected = [];
                this.queueCommit();
            },

            init() {
                /* Anything that flips the selection SHAPE — the escalation
                   controls, deselect-all, the server's scope reset pushed down
                   on a filter or search change — invalidates the range state:
                   a base carried across would be unioned back by the next
                   Shift+range and resurrect rows nobody sees selected. Range
                   gestures themselves never write the mode, so this watcher
                   cannot fire mid-gesture. */
                this.$watch('mode', () => this.clearAnchor());
            },

            setAnchor(key) {
                this.anchorKey = key;
                this.baseSelection = null;
            },

            clearAnchor() {
                this.anchorKey = null;
                this.baseSelection = null;
            },

            /* Bounds of the contiguous selected block the given row sits in,
               or null when the row itself is unselected. In all mode
               isSelected() is inverted, so "contiguous block" means a block
               of un-excluded rows there — deliberate (ADR 0024). */
            blockBounds(rows, idx) {
                const selectedAt = (i) => this.isSelected(rows[i].dataset.rowKey);

                if (! selectedAt(idx)) return null;

                let start = idx;
                let end = idx;
                while (start > 0 && selectedAt(start - 1)) start--;
                while (end < rows.length - 1 && selectedAt(end + 1)) end++;

                return { start, end };
            },

            /* Where a Shift+range grows from when no gesture has set an anchor
               itself: the far edge of the contiguous selected block the given
               row sits in. A selection made with mod+A, a checkbox or the
               select-all strip carries no anchor, and without this the first
               Shift+arrow would replace the whole selection with a two-row
               range — every other row silently deselected. Anchoring at the
               far edge makes that first Shift+arrow shrink or grow the block
               the user is looking at instead. */
            anchorFor(rows, idx) {
                const key = rows[idx]?.dataset.rowKey ?? null;
                if (key === null) return key;

                const block = this.blockBounds(rows, idx);
                if (block === null) return key;

                return (idx - block.start >= block.end - idx ? rows[block.start] : rows[block.end]).dataset.rowKey;
            },

            /* Write base ∪ [anchor … active] block. A range gesture NEVER
               rewrites the mode: in all mode `selected` holds the exclusions,
               so the same write means the range becomes deselected — and
               forcing keys mode here is exactly what used to collapse an
               all-matching selection down to one page block.

               The base is the snapshot minus the contiguous block around the
               anchor, taken on the first write: an explicit COPY of
               `selected` (the entangle proxy would change under our hands on
               that very write), and minus the block so the gesture owns the
               block it grows and shrinks while everything selected elsewhere
               survives the range. */
            selectRange(rows, activeIdx) {
                const anchorIdx = rows.findIndex((row) => row.dataset.rowKey === this.anchorKey);
                if (anchorIdx < 0) return;

                if (this.baseSelection === null) {
                    const block = this.blockBounds(rows, anchorIdx);
                    const blockKeys = block === null
                        ? []
                        : rows.slice(block.start, block.end + 1).map((row) => row.dataset.rowKey);

                    this.baseSelection = [...this.selected].filter((key) => ! blockKeys.includes(key));
                }

                const from = Math.min(anchorIdx, activeIdx);
                const to = Math.max(anchorIdx, activeIdx);
                const range = rows.slice(from, to + 1).map((row) => row.dataset.rowKey);

                this.selected = [...new Set([...this.baseSelection, ...range])];
                this.queueCommit();
            },

            /* mod+A: select the whole page — a union, like the header
               checkbox, so work on other pages survives. NOT a range gesture:
               in all mode everything is already selected, and unioning page
               keys into the EXCLUSIONS would deselect the page instead, so it
               stands down. Drops the range base: after a page select the next
               range grows from the new reality, not a stale snapshot. */
            selectPage() {
                if (this.selectsAll) return;

                this.baseSelection = null;
                this.selected = [...new Set([...this.selected, ...this.pageKeys])];
                this.queueCommit();
            },

            queueCommit() {
                if (! syncLive) return;

                clearTimeout(this.commitTimer);
                this.commitTimer = setTimeout(() => this.$wire.$commit(), commitDelay);
            },
        };
    });
});

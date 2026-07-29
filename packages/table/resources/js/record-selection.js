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
 * asset partial inlines this file verbatim (inside an IIFE of its own, the
 * wrapper esbuild gives the bundle) as the fallback — a dangling
 * `wireRecordSelection` reference would otherwise kill Alpine for the whole
 * table wrapper (search, filters, bulk bar, pagination, modal hosts).
 */

// A function, NOT an arrow: Alpine binds a data factory's `this` to the
// magic context that carries $wire.
function wireRecordSelection(config = {}) {
    const statePath = config.statePath || 'tableState.selection';
    const syncLive = !! config.syncLive;
    const commitDelay = config.commitDelay ?? 350;
    /* Ready-made strings from PHP: the live region needs whole sentences,
       which only the server can pluralise and translate. */
    const announcements = config.announcements || {};

    /* This component's OWN root element — the one carrying data-page-keys and
       data-matching — captured in init() and held in the closure.

       NOT `$root` at read time, and this is the whole bug it fixes: Alpine
       resolves a magic against the ELEMENT THE EXPRESSION IS EVALUATED ON, not
       against the component that owns the method. `fillHandle()` wraps the
       table in its own x-data INSIDE this root, so `toggleAll()` fired from the
       header checkbox saw `$root` = the fill root, read no data-page-keys off
       it, and unioned the selection with nothing — the select-all checkbox
       selected zero rows.

       The closure, rather than a property on the returned object, for the same
       reason: `this` inside these getters is the merged scope proxy of whatever
       component stack the expression ran in, so ANY property read through it
       can be shadowed by a nested component. A closure variable cannot be.

       Holds the ELEMENT, never its values: the root is keyed
       (wire:key=table-wrapper), so Livewire morphs it in place and the datasets
       below stay live — a seeded count would freeze at the first render's
       value (filter 128k rows down to 7 and the bar would still say 128k). */
    let root = null;

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

        /* Read from the DOM (off the captured root — see `root` above), not
           seeded into the component: the root is keyed (wire:key
           table-wrapper), so Livewire morphs it in place and a seeded value
           would keep the first render's count forever (filter 128k rows down
           to 7 and the bar would still say 128k). */
        get matching() { return Number(root?.dataset.matching || 0); },
        get pageKeys() { return JSON.parse(root?.dataset.pageKeys || '[]'); },
        get selectsAll() { return this.mode === 'all'; },
        get selectedCount() { return this.selectsAll ? Math.max(0, this.matching - this.selected.length) : this.selected.length; },
        get allSelected() { return this.pageKeys.length > 0 && this.pageKeys.every((k) => this.isSelected(k)); },
        get someSelected() { return this.selectedCount > 0 && ! this.allSelected; },

        isSelected(key) {
            return this.selectsAll ? ! this.selected.includes(key) : this.selected.includes(key);
        },

        /* Stays false until the selection first changes. A live region
           announces CHANGES to its content, so filling it during boot
           would have a screen reader read out a selection the user has
           not made yet — and a table can render with rows already
           selected from the server. */
        announceReady: false,

        /* What the live region says. Empty until the first change, so the
           region is present and silent from the start (it has to be in the
           DOM all along: a region added or revealed at the moment it gains
           text announces nothing). */
        get announcement() {
            if (! this.announceReady) return '';

            if (this.selectsAll) {
                return (announcements.all || '').replace(':total', this.matching);
            }

            if (this.selectedCount === 0) return announcements.none || '';

            return (announcements.some || '')
                .replace(':count', this.selectedCount)
                .replace(':total', this.matching);
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
            /* The one place a magic can be trusted: init() runs on this
               component's own element, so the innermost scope in the stack is
               this component and `$el` is the element that declared it. Take
               the reading once, here, and never ask again.

               `closest()` on top of it because it costs nothing and cannot be
               wrong: from the declaring element it returns that element, and
               from anywhere below it walks up to the root that actually
               carries the dataset. */
            const el = this.$el ?? null;
            root = el?.closest?.('[data-selection-root]') ?? el;

            /* Anything that flips the selection SHAPE — the escalation
               controls, deselect-all, the server's scope reset pushed down
               on a filter or search change — invalidates the range state:
               a base carried across would be unioned back by the next
               Shift+range and resurrect rows nobody sees selected. Range
               gestures themselves never write the mode, so this watcher
               cannot fire mid-gesture. */
            this.$watch('mode', () => this.clearAnchor());

            /* Arm the live region on the first change of either half of the
               selection state. Re-announcing an unchanged sentence is
               harmless — a live region only speaks when its text actually
               differs — so no guard against repeat writes is needed. */
            this.$watch('selected', () => { this.announceReady = true; });
            this.$watch('mode', () => { this.announceReady = true; });
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
        selectRange(rows, activeIdx, { additive = false } = {}) {
            const anchorIdx = rows.findIndex((row) => row.dataset.rowKey === this.anchorKey);
            if (anchorIdx < 0) return;

            if (this.baseSelection === null) {
                // Additive (mod+Shift): the block is purely added, so the
                // base keeps the whole snapshot instead of ceding the
                // block around the anchor to the range.
                const block = additive ? null : this.blockBounds(rows, anchorIdx);
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
}

// ─── Self-registration ──────────────────────────────────────────
// `alpine:init` fires exactly once per document, so a bundle that only listens
// for it registers nothing when it arrives after a `wire:navigate`. Register
// straight away when Alpine is already running; keep the listener for the
// first, cold load. The `registered` guard is load-bearing: the same src can be
// emitted twice (a per-surface partial plus the layout tag).
//
// `let`, and it stays `let`: esbuild wraps this file in an IIFE for the bundle,
// and the inline fallback is wrapped in one by selection-assets.blade.php, so
// this name is function-scoped on both paths. Bare at the top level of a
// classic <script> it would be a global lexical binding (a second copy = a
// SyntaxError); `var` would only trade that for a `window.registered` leak.
let registered = false;

function registerWireRecordSelection() {
    if (registered || ! window.Alpine) return;
    registered = true;

    window.Alpine.data('wireRecordSelection', wireRecordSelection);
}

if (window.Alpine) {
    // Alpine already started (e.g. the script loaded after a Livewire navigation).
    registerWireRecordSelection();
} else {
    document.addEventListener('alpine:init', registerWireRecordSelection);
}

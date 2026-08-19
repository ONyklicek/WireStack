/**
 * wireSortable — the drag controller behind row reordering and column
 * reordering on a wire-table.
 *
 * One Alpine component wraps the whole table (see
 * `wire-sortable::tables.index`) and owns up to two SortableJS instances: one on
 * `<tbody>` for rows, one on the header `<tr>` for columns. Both persist through
 * the host Livewire component (`reorderRows` / `reorderColumns`).
 *
 * SortableJS is bundled in from npm rather than fetched from a CDN, so the
 * package works offline and under a strict CSP. `config('wire-sortable.sortablejs_cdn')`
 * still loads an extra copy for apps that set it; the two do not collide because
 * this controller uses the bundled import, not `window.Sortable`.
 */

import Sortable from 'sortablejs';

// One pair of morph hooks for the page rather than one pair per table:
// Livewire's hook() has no off switch, so registering inside init() stacks a
// fresh pair every time a controller initialises — a second reorderable table,
// a wire:navigate, a table inside a lazily loaded modal — and every stacked
// copy keeps running against a component that no longer exists. Same pattern as
// wire-table's record-actions guard and the fill handle's.
//
// Keyed by the wrapper element rather than held in a Set, because a controller
// cannot reliably remove *itself*: Alpine calls destroy() with a merge proxy of
// the scope, not the instance init() saw, so `delete(this)` deletes nothing.
// The element is the same object in both, and keying by it also means a
// controller replacing another on the same wrapper takes over its entry instead
// of joining it.
const controllers = new Map();
let morphGuardsInstalled = false;

/** Drop controllers whose wrapper has left the document, then run the rest. */
const eachController = (run) => {
    controllers.forEach((controller, root) => {
        if (!root.isConnected) {
            controllers.delete(root);
            return;
        }

        run(controller);
    });
};

const installMorphGuards = () => {
    if (morphGuardsInstalled || !window.Livewire) return;

    morphGuardsInstalled = true;

    window.Livewire.hook('morph.updating', ({ el, skip }) => {
        eachController((controller) => controller.onMorphUpdating(el, skip));
    });

    window.Livewire.hook('morph.updated', ({ el }) => {
        eachController((controller) => controller.onMorphUpdated(el));
    });

    // A row partial is morphed by wire-core's own applier, which drives
    // Alpine.morph() directly and never reaches the hook above. The drag handle
    // <td> is client-side markup the server render knows nothing about, so a
    // partial replaced a three-cell row with the server's two-cell one and left
    // that row undraggable — silently, and only for rows somebody had edited.
    //
    // This is an announcement, not a hook: it says what was replaced and leaves
    // each listener to repair what it owns. Unlike `morph.updated` it is honoured
    // while a cell is being edited, because a partial IS the answer to that
    // cell's save and re-adding a missing handle neither destroys anything nor
    // moves focus.
    document.addEventListener('wire:partials-applied', ({ detail }) => {
        eachController((controller) => controller.onPartialsApplied(detail?.elements ?? []));
    });
};

export function wireSortable(config = {}) {
    return {
        rowSortableInstance: null,
        columnSortableInstance: null,
        isDragging: false,
        orderBeforeDrag: null,
        config: {
            rowReorderable: config.rowReorderable ?? false,
            columnReorderable: config.columnReorderable ?? false,
            orderColumn: config.orderColumn ?? 'sort_order',
            animation: config.animation ?? 150,
            dragHandleHtml: config.dragHandleHtml ?? '',
        },
        isReordering: config.isReordering ?? false,

        init() {
            this.scheduleSetup();

            this.$watch('isReordering', () => {
                this.scheduleSetup();
            });

            // The morph guards below run from the page-wide hooks, for as long
            // as this controller owns the wrapper.
            controllers.set(this.$root, this);
            installMorphGuards();
        },

        /**
         * Block the morph during a drag, and over the one cell being edited —
         * dragging moves rows the server render knows nothing about, and
         * morphing an editable cell can replace the input being typed into.
         *
         * Both guards have to name what they protect. `skip()` takes the whole
         * subtree of `el` with it and `contains()` is inclusive, so the old
         * "is any input inside the table focused?" test — answered for every
         * node from the wrapper down — skipped the wrapper itself, and with it
         * the entire table. The search box is an input inside the table: typing
         * in it silenced every render it asked for, and the table simply
         * stopped responding to search.
         */
        onMorphUpdating(el, skip) {
            if (!this.$root.contains(el)) return;

            if (this.isDragging) {
                skip();
                return;
            }

            const cell = this.editingCell();
            if (cell && el === cell) skip();
        },

        /**
         * Put back the drag handles a row partial took with it.
         *
         * Deliberately narrower than `onMorphUpdated`: no `destroyRowSortable()`,
         * no re-init, no width lock. `addRowDragHandles()` skips any row that
         * still has a handle, so this touches exactly the rows that were
         * replaced — which is why it is safe to run while a cell is focused,
         * where a full setup() would collapse the table layout and kill focus.
         *
         * SortableJS is bound to the <tbody>, not to the rows, so a replaced row
         * needs its handle back and nothing else to be draggable again.
         */
        onPartialsApplied(elements) {
            if (this.isDragging) return;
            if (!elements.some((el) => this.$root.contains(el))) return;

            // Two repairs with two different preconditions: handles exist only
            // in row-reorder mode, while column order is the client's whenever
            // headers are draggable — including on a table that has no row
            // reordering at all, which is the shape `columnReorderable()` alone
            // renders.
            if (this.config.rowReorderable && this.isReordering) {
                const tbody = this.$root.querySelector('tbody');
                if (tbody) this.addRowDragHandles(tbody);
            }

            this.reorderPartialColumns();
        },

        /**
         * Put a replaced row's cells back into the column order the headers show.
         *
         * The smaller half of the same bug. Between a header drag and the server
         * persisting it, the body carries the client's order while the server
         * still renders its own — so a partial arriving in that window puts one
         * row back into the old order while every other row keeps the new one.
         *
         * `reorderBodyColumns()` only moves cells carrying `data-column`, so the
         * drag handle and any leading/trailing cell stay where they are, and it
         * is a no-op once the two orders agree.
         */
        reorderPartialColumns() {
            if (!this.config.columnReorderable) return;

            const thead = this.$root.querySelector('thead');
            if (!thead) return;

            this.reorderBodyColumns(this.headerColumnOrder(thead));
        },

        /** Re-initialize after Livewire morphs (pagination, filters, etc.). */
        onMorphUpdated(el) {
            if (this.isDragging || !this.$root.contains(el)) return;

            // Not while a cell is being edited: setup() destroys and re-creates
            // the drag-handle <td> cells, which collapses the table layout and
            // kills focus. Focus anywhere else in the table — the search box, a
            // filter, the per-page select — is outside <tbody> and survives it.
            if (this.editingCell()) return;

            this.scheduleSetup();
        },

        /**
         * The editable cell the user is currently typing in, if any.
         *
         * Identified by the `[data-record-key][data-column-name]` pair the
         * editable columns render, which is the selector wireTableLive already
         * reads in busy() — no new convention, and one place to change if the
         * cell markup ever does.
         */
        editingCell() {
            const active = document.activeElement;

            if (!active || !this.$root.contains(active) || !active.closest) return null;

            return active.closest('[data-record-key][data-column-name]');
        },

        /**
         * One setup() per morph, not one per morphed node.
         *
         * `morph.updated` fires for every element Livewire patches, which over
         * a table is hundreds of nodes — each of them used to tear down and
         * rebuild both Sortable instances.
         */
        scheduleSetup() {
            if (this._setupQueued) return;

            this._setupQueued = true;

            this.$nextTick(() => {
                this._setupQueued = false;
                this.setup();
            });
        },

        setup() {
            this.destroyRowSortable();

            if (this.config.rowReorderable && this.isReordering) {
                this.initRowSortable();
            }

            if (this.config.columnReorderable) {
                this.initColumnSortable();
            }
        },

        // ── Row Reordering ──────────────────────────────────

        initRowSortable() {
            const tbody = this.$root.querySelector('tbody');
            if (!tbody) return;

            this.addRowDragHandles(tbody);

            this.rowSortableInstance = new Sortable(tbody, {
                handle: '.wire-sortable-handle',
                animation: this.config.animation,
                easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                ghostClass: 'wire-sortable-ghost',
                chosenClass: 'wire-sortable-chosen',
                dragClass: 'wire-sortable-drag',
                forceFallback: true,
                fallbackClass: 'wire-sortable-fallback',
                fallbackTolerance: 3,
                scrollSensitivity: 80,
                scrollSpeed: 12,

                // Lock cell widths before drag so ghost + clone keep layout
                onChoose: (evt) => {
                    this.lockTableCellWidths(tbody);
                },

                onUnchoose: () => {
                    this.unlockTableCellWidths(tbody);
                },

                onStart: (evt) => {
                    this.isDragging = true;
                    this.pausePolling();
                    document.body.classList.add('wire-sortable-active');

                    // The order before the drag, so onEnd can send the rows that
                    // actually moved rather than the whole tbody.
                    this.orderBeforeDrag = this.rowKeys(tbody);

                    // Set explicit height on ghost to prevent collapse
                    evt.item.style.height = evt.item.offsetHeight + 'px';
                },

                onEnd: (evt) => {
                    this.isDragging = false;
                    this.resumePolling();
                    document.body.classList.remove('wire-sortable-active');

                    evt.item.style.height = '';
                    this.unlockTableCellWidths(tbody);

                    if (evt.oldIndex === evt.newIndex) return;

                    const before = this.orderBeforeDrag;
                    this.orderBeforeDrag = null;

                    const items = this.reorderPayload(before, this.rowKeys(tbody));

                    if (items.length > 0) {
                        this.getLivewireComponent()?.call('reorderRows', items);
                    }
                },
            });
        },

        /**
         * Fix cell widths so rows don't collapse when pulled out of table flow.
         */
        lockTableCellWidths(tbody) {
            const table = tbody.closest('table');
            if (!table) return;

            // Lock header widths
            table.querySelectorAll('thead th').forEach(th => {
                th.style.width = th.offsetWidth + 'px';
            });

            // Lock body cell widths
            tbody.querySelectorAll('tr').forEach(tr => {
                tr.querySelectorAll('td').forEach(td => {
                    td.style.width = td.offsetWidth + 'px';
                    td.style.minWidth = td.offsetWidth + 'px';
                    td.style.maxWidth = td.offsetWidth + 'px';
                });
            });

            // Lock table width to prevent reflow
            table.style.tableLayout = 'fixed';
            table.style.width = table.offsetWidth + 'px';
        },

        unlockTableCellWidths(tbody) {
            const table = tbody?.closest('table');
            if (!table) return;

            table.style.tableLayout = '';
            table.style.width = '';

            table.querySelectorAll('thead th').forEach(th => {
                th.style.width = '';
            });

            tbody.querySelectorAll('td').forEach(td => {
                td.style.width = '';
                td.style.minWidth = '';
                td.style.maxWidth = '';
            });
        },

        /** The record keys currently in the tbody, in visual order. */
        rowKeys(tbody) {
            return Array.from(tbody.querySelectorAll('tr[wire\\:key]'))
                .map((row) => (row.getAttribute('wire:key') ?? '').replace('row-', ''))
                .filter(Boolean);
        },

        /**
         * The rows a drop has to tell the server about — the moved range, not the
         * page.
         *
         * A drop used to send every row in the tbody, so the write cost one
         * UPDATE per row on the page however far anything moved: measured, the
         * query count rose 1:1 with the row count. `alwaysReorderable()` drops
         * pagination, so "the page" can be the entire table.
         *
         * Sending a contiguous slice is safe because of what the server does with
         * it: `resolveReorderSlots()` redistributes the sent rows' OWN existing
         * order values among themselves and leaves every row it was not sent
         * alone. So the slice between the first and last position that changed
         * lands byte-identical order values to the full payload — pinned by
         * `ReorderWriteCostTest`.
         *
         * `order` stays the row's absolute 1-based position rather than its index
         * within the slice: the server ignores it, but `canReorder()`,
         * `beforeReorder()` and `afterReorder()` are handed the payload and a
         * consumer may read it.
         *
         * Falls back to the whole tbody when the before/after lists cannot be
         * compared — a morph that added or removed a row mid-drag — because the
         * full payload is always correct, only expensive.
         */
        reorderPayload(before, after) {
            const item = (key, index) => ({ value: key, order: index + 1 });

            if (! Array.isArray(before) || before.length !== after.length) {
                return after.map(item);
            }

            let first = 0;
            while (first < after.length && before[first] === after[first]) first++;

            // Identical lists: the drop put everything back where it was.
            if (first === after.length) return [];

            let last = after.length - 1;
            while (last > first && before[last] === after[last]) last--;

            return after.slice(first, last + 1).map((key, i) => item(key, first + i));
        },

        addRowDragHandles(tbody) {
            const table = tbody.closest('table');
            if (!table) return;

            const thead = table.querySelector('thead');

            // Add header cell for drag handle column
            if (thead && !thead.querySelector('.wire-sortable-th')) {
                thead.querySelectorAll('tr').forEach((tr) => {
                    const th = document.createElement('th');
                    th.className = 'wire-sortable-th';
                    th.scope = 'col';
                    tr.prepend(th);
                });
            }

            // Add drag handle cells to body rows
            tbody.querySelectorAll('tr').forEach((tr) => {
                if (tr.querySelector('.wire-sortable-handle')) return;

                const td = document.createElement('td');
                td.className = 'wire-sortable-handle-cell';
                td.innerHTML = this.getDragHandleHtml();
                tr.prepend(td);
            });
        },

        destroyRowSortable() {
            if (this.rowSortableInstance) {
                this.rowSortableInstance.destroy();
                this.rowSortableInstance = null;
            }

            const tbody = this.$root.querySelector('tbody');
            if (tbody) {
                this.unlockTableCellWidths(tbody);
                tbody.querySelectorAll('.wire-sortable-handle-cell').forEach(el => el.remove());
            }

            const thead = this.$root.querySelector('thead');
            if (thead) {
                thead.querySelectorAll('.wire-sortable-th').forEach(el => el.remove());
            }
        },

        getDragHandleHtml() {
            // Markup owned by the wire-sortable::partials.drag-handle Blade
            // partial and injected via config; see Table::getDragHandleHtml().
            return this.config.dragHandleHtml;
        },

        // ── Column Reordering ───────────────────────────────

        initColumnSortable() {
            const thead = this.$root.querySelector('thead tr');
            if (!thead) return;

            if (this.columnSortableInstance) {
                this.columnSortableInstance.destroy();
                this.columnSortableInstance = null;
            }

            this.markHeaderCells(thead);

            const draggableSelector = 'th[data-sortable-column]';

            this.columnSortableInstance = new Sortable(thead, {
                animation: this.config.animation,
                easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
                draggable: draggableSelector,
                ghostClass: 'wire-sortable-column-ghost',
                chosenClass: 'wire-sortable-column-chosen',
                dragClass: 'wire-sortable-column-drag',
                direction: 'horizontal',
                forceFallback: true,
                fallbackClass: 'wire-sortable-column-fallback',
                fallbackTolerance: 3,

                onStart: () => {
                    this.isDragging = true;
                    this.pausePolling();
                },

                onEnd: (evt) => {
                    this.isDragging = false;
                    this.resumePolling();

                    if (evt.oldIndex === evt.newIndex) return;

                    const columnOrder = this.headerColumnOrder(thead);

                    this.reorderBodyColumns(columnOrder);

                    if (columnOrder.length > 0) {
                        this.getLivewireComponent()?.call('reorderColumns', columnOrder);
                    }
                },
            });
        },

        /**
         * The column order the headers are currently in.
         *
         * Read from the DOM rather than kept as state, because the headers ARE
         * the record of a drag until the server confirms it — which is also what
         * makes it the right answer for a row that arrives mid-flight.
         */
        headerColumnOrder(thead) {
            const order = [];

            thead?.querySelectorAll('th[data-sortable-column]').forEach((th) => {
                const name = th.getAttribute('data-sortable-column');
                if (name) order.push(name);
            });

            return order;
        },

        markHeaderCells(thead) {
            thead.querySelectorAll('th').forEach((th) => {
                if (th.hasAttribute('data-sortable-column') || th.classList.contains('wire-sortable-th')) {
                    return;
                }

                const wireClick = th.querySelector('[wire\\:click*="sortTable"]');
                if (wireClick) {
                    const match = wireClick.getAttribute('wire:click')?.match(/sortTable\('([^']+)'\)/);
                    if (match) {
                        th.setAttribute('data-sortable-column', match[1]);
                        th.style.cursor = 'grab';
                        return;
                    }
                }

                const dataCol = th.getAttribute('data-column');
                if (dataCol) {
                    th.setAttribute('data-sortable-column', dataCol);
                    th.style.cursor = 'grab';
                }
            });
        },

        /*
         * Mirror the header's new column order onto the body rows, matched
         * by column NAME.
         *
         * It used to move cells by the index Sortable reported for the
         * header, which assumes a body row is the header row with different
         * tags. It is not: a row leads with the teleport <template> that
         * carries its context menu, and the selection cell, the drag handle
         * and the actions column each sit on one side or the other. Every
         * one of those shifts the offset, so the move landed on whichever
         * cell happened to occupy that position — dragging a column header
         * would park the checkbox column between two data columns while the
         * data itself never moved.
         */
        reorderBodyColumns(columnOrder) {
            const tbody = this.$root.querySelector('tbody');
            if (!tbody || !columnOrder || columnOrder.length === 0) return;

            // Direct children only: a sub-row lives in a nested table with
            // its own columns, and a group header is a single colspan cell.
            Array.from(tbody.children)
                .filter((tr) => tr.matches('tr[data-row-key]'))
                .forEach((tr) => {
                    const cells = new Map();

                    Array.from(tr.children).forEach((cell) => {
                        const name = cell.getAttribute && cell.getAttribute('data-column');
                        if (name) cells.set(name, cell);
                    });

                    if (cells.size === 0) return;

                    // Re-insert the column cells in header order ahead of
                    // whatever followed the block, so a trailing actions
                    // cell stays trailing (and a leading one, leading).
                    const ordered = Array.from(cells.values());
                    const anchor = ordered[ordered.length - 1].nextSibling;

                    columnOrder.forEach((name) => {
                        const cell = cells.get(name);
                        if (cell) tr.insertBefore(cell, anchor);
                    });
                });
        },

        // ── Helpers ──────────────────────────────────────────

        pausePolling() {
            const wrapper = this.$root.closest('[wire\\:id]');
            if (!wrapper) return;

            const pollEl = wrapper.querySelector('[wire\\:poll]')
                || (wrapper.hasAttribute('wire:poll') ? wrapper : null);
            if (!pollEl) return;

            const attrs = pollEl.getAttributeNames().filter(a => a.startsWith('wire:poll'));
            if (attrs.length === 0) return;

            this._pausedPoll = attrs.map(attr => ({
                el: pollEl,
                attr,
                value: pollEl.getAttribute(attr),
            }));

            attrs.forEach(attr => pollEl.removeAttribute(attr));
        },

        resumePolling() {
            if (!this._pausedPoll) return;

            this._pausedPoll.forEach(({ el, attr, value }) => {
                el.setAttribute(attr, value || '');
            });

            this._pausedPoll = null;
        },

        getLivewireComponent() {
            const wireEl = this.$root.closest('[wire\\:id]');
            if (!wireEl) return null;

            return Livewire.find(wireEl.getAttribute('wire:id'));
        },

        destroy() {
            // Alpine calls this when the tree carrying the wrapper is torn
            // down. Leaving the entry behind would keep a destroyed component
            // answering for the table: its $root is often the very same element
            // the next controller drives, so a stale `isDragging` would go on
            // blocking morphs for the whole page. Removed by element, since
            // `this` here is not the instance that registered (see above).
            controllers.delete(this.$root);

            this.destroyRowSortable();
            if (this.columnSortableInstance) {
                this.columnSortableInstance.destroy();
                this.columnSortableInstance = null;
            }
        },
    };
}

// ─── Self-registration ──────────────────────────────────────────
// Unconditional and idempotent, per architecture/plans/js-asset-registration.md:
// `alpine:init` fires exactly once per document, so a bundle that arrives after
// the first page render — a Livewire navigation, a table inside a lazily loaded
// modal — would register nothing at all if it only listened for that event. The
// `registered` guard makes a second delivery of the same src a no-op.
let registered = false;
function register() {
    if (registered || !window.Alpine) return;
    registered = true;
    window.Alpine.data('wireSortable', wireSortable);
}

if (window.Alpine) {
    // Alpine already started (e.g. script loaded after a Livewire navigation).
    register();
} else {
    document.addEventListener('alpine:init', register);
}

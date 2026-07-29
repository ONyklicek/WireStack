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

export function wireSortable(config = {}) {
    return {
        rowSortableInstance: null,
        columnSortableInstance: null,
        isDragging: false,
        config: {
            rowReorderable: config.rowReorderable ?? false,
            columnReorderable: config.columnReorderable ?? false,
            orderColumn: config.orderColumn ?? 'sort_order',
            animation: config.animation ?? 150,
            dragHandleHtml: config.dragHandleHtml ?? '',
        },
        isReordering: config.isReordering ?? false,

        init() {
            this.$nextTick(() => this.setup());

            this.$watch('isReordering', () => {
                this.$nextTick(() => this.setup());
            });

            // Block Livewire morph during drag or inline editing to prevent DOM disruption.
            // setup() re-creates drag-handle <td> cells which collapses the table
            // layout and kills focus, and morphing itself can replace the focused
            // input element.
            Livewire.hook('morph.updating', ({ el, skip }) => {
                if (!this.$root.contains(el)) return;

                if (this.isDragging) {
                    skip();
                    return;
                }

                const active = document.activeElement;
                if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')
                    && this.$root.contains(active)) {
                    skip();
                }
            });

            // Re-initialize after Livewire morphs (pagination, filters, etc.)
            Livewire.hook('morph.updated', ({ el }) => {
                if (this.isDragging || !this.$root.contains(el)) return;

                // Skip re-init when a table input is focused — setup() destroys
                // and re-creates drag-handle <td> cells which collapses the table
                // layout and kills focus on editable columns.
                const active = document.activeElement;
                if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')
                    && this.$root.contains(active)) {
                    return;
                }

                this.$nextTick(() => this.setup());
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

                    const rows = tbody.querySelectorAll('tr[wire\\:key]');
                    const items = [];

                    rows.forEach((row, index) => {
                        const wireKey = row.getAttribute('wire:key');
                        const value = wireKey ? wireKey.replace('row-', '') : null;
                        if (value) {
                            items.push({ value: value, order: index + 1 });
                        }
                    });

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

                    const headers = thead.querySelectorAll('th[data-sortable-column]');
                    const columnOrder = [];
                    headers.forEach((th) => {
                        const name = th.getAttribute('data-sortable-column');
                        if (name) columnOrder.push(name);
                    });

                    this.reorderBodyColumns(columnOrder);

                    if (columnOrder.length > 0) {
                        this.getLivewireComponent()?.call('reorderColumns', columnOrder);
                    }
                },
            });
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

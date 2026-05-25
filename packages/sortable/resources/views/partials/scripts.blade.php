@php
    $cdnUrl = config('wire-sortable.sortablejs_cdn');
@endphp

@if($cdnUrl)
<script src="{{ $cdnUrl }}"></script>
@endif

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('wireSortable', (config = {}) => ({
            rowSortableInstance: null,
            columnSortableInstance: null,
            isDragging: false,
            config: {
                rowReorderable: config.rowReorderable ?? false,
                columnReorderable: config.columnReorderable ?? false,
                orderColumn: config.orderColumn ?? 'sort_order',
                animation: config.animation ?? 150,
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
                return `
                    <div class="wire-sortable-handle">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <circle cx="5.5" cy="3.5" r="1.5"/>
                            <circle cx="10.5" cy="3.5" r="1.5"/>
                            <circle cx="5.5" cy="8" r="1.5"/>
                            <circle cx="10.5" cy="8" r="1.5"/>
                            <circle cx="5.5" cy="12.5" r="1.5"/>
                            <circle cx="10.5" cy="12.5" r="1.5"/>
                        </svg>
                    </div>
                `;
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

                        this.reorderBodyColumns(evt.oldIndex, evt.newIndex);

                        const headers = thead.querySelectorAll('th[data-sortable-column]');
                        const columnOrder = [];
                        headers.forEach((th) => {
                            const name = th.getAttribute('data-sortable-column');
                            if (name) columnOrder.push(name);
                        });

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

            reorderBodyColumns(oldIndex, newIndex) {
                const tbody = this.$root.querySelector('tbody');
                if (!tbody) return;

                tbody.querySelectorAll('tr').forEach((tr) => {
                    const cells = Array.from(tr.children);
                    if (oldIndex >= cells.length || newIndex >= cells.length) return;

                    const movedCell = cells[oldIndex];
                    const referenceCell = cells[newIndex];

                    if (oldIndex < newIndex) {
                        referenceCell.after(movedCell);
                    } else {
                        referenceCell.before(movedCell);
                    }
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
        }));
    });
</script>

<style>
    /* ── Drag Handle ─────────────────────────────────── */

    .wire-sortable-th {
        width: 2.5rem;
        padding: 0.75rem 0 0.75rem 0.75rem;
    }

    .wire-sortable-handle-cell {
        width: 2.5rem;
        padding: 0.75rem 0 0.75rem 0.75rem;
        vertical-align: middle;
    }

    .wire-sortable-handle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 0.375rem;
        cursor: grab;
        color: rgb(156 163 175);
        transition: color 150ms ease, background-color 150ms ease;
        user-select: none;
        -webkit-user-select: none;
    }

    .wire-sortable-handle:hover {
        color: rgb(107 114 128);
        background-color: rgb(243 244 246);
    }

    .wire-sortable-handle:active {
        cursor: grabbing;
        color: rgb(79 70 229);
        background-color: rgb(238 242 255);
    }

    .dark .wire-sortable-handle {
        color: rgb(107 114 128);
    }

    .dark .wire-sortable-handle:hover {
        color: rgb(209 213 219);
        background-color: rgb(55 65 81);
    }

    .dark .wire-sortable-handle:active {
        color: rgb(129 140 248);
        background-color: rgb(49 46 129 / 0.4);
    }

    /* ── Row Ghost (placeholder left behind) ─────────── */

    .wire-sortable-ghost {
        background-color: rgb(243 244 246);
    }

    .wire-sortable-ghost > td {
        opacity: 0;
    }

    .dark .wire-sortable-ghost {
        background-color: rgb(55 65 81);
    }

    /* ── Row Chosen (before drag starts moving) ──────── */

    .wire-sortable-chosen {
        background-color: inherit;
    }

    /* ── Row Drag / Fallback Clone ────────────────────── */

    .wire-sortable-drag,
    .wire-sortable-fallback {
        background-color: white !important;
        box-shadow:
            0 10px 30px -5px rgb(0 0 0 / 0.12),
            0 4px 12px -2px rgb(0 0 0 / 0.08),
            0 0 0 1px rgb(0 0 0 / 0.04) !important;
        border-radius: 0.5rem !important;
        opacity: 1 !important;
        z-index: 9999 !important;
    }

    .dark .wire-sortable-drag,
    .dark .wire-sortable-fallback {
        background-color: rgb(31 41 55) !important;
        box-shadow:
            0 10px 30px -5px rgb(0 0 0 / 0.4),
            0 4px 12px -2px rgb(0 0 0 / 0.3),
            0 0 0 1px rgb(255 255 255 / 0.05) !important;
    }

    /* ── Column Ghost ────────────────────────────────── */

    .wire-sortable-column-ghost {
        opacity: 0.3;
        background-color: rgb(238 242 255);
    }

    .dark .wire-sortable-column-ghost {
        background-color: rgb(49 46 129 / 0.3);
    }

    .wire-sortable-column-chosen {
        background-color: inherit;
    }

    .wire-sortable-column-drag,
    .wire-sortable-column-fallback {
        background-color: rgb(249 250 251) !important;
        box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.1) !important;
        border-radius: 0.375rem !important;
        opacity: 1 !important;
    }

    .dark .wire-sortable-column-drag,
    .dark .wire-sortable-column-fallback {
        background-color: rgb(31 41 55) !important;
    }

    [data-sortable-column] {
        transition: background-color 150ms ease;
    }

    [data-sortable-column]:active {
        cursor: grabbing;
    }

    /* ── Global: disable text selection during drag ──── */

    .wire-sortable-active {
        user-select: none;
        -webkit-user-select: none;
        cursor: grabbing !important;
    }

    .wire-sortable-active * {
        cursor: grabbing !important;
    }
</style>

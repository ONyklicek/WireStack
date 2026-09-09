/**
 * wireSortableList — drag-to-reorder for a list whose items are addressed by
 * their position: a Repeater's cards, a Repeater table's rows, a Builder's
 * blocks.
 *
 * It exists because those three views shipped `x-sortable`, `x-sortable-item`
 * and `x-sortable-handle` for months against a directive **nothing in this
 * repository registers**. The handle rendered, the cursor said `grab`, and
 * dragging did nothing; `reorderRepeaterItems()` was correct and complete on the
 * server and simply never called from a browser. Every test reached it through
 * `->call(...)`, and the one driver that looked at a handle asserted only that
 * it existed.
 *
 * **Why core, and why its own bundle.** Forms is below table in the package
 * graph, so it cannot borrow `wire-sortable`; core is the lowest layer that can
 * own the behaviour, and a future consumer (an infolist, a panel widget list)
 * gets it for free. It ships separately from `wire-core-dropdown.js` because
 * SortableJS is ~45 kB and only a reorderable list needs it — the same argument
 * that moved `wireFillHandle` out of the dropdown bundle (ADR 0025 § step 10).
 *
 * **Relationship to `wire-sortable`.** That package's `wireSortable` is the
 * *table* controller: two Sortable instances, injected handle cells, column
 * reordering, width locking and partial-morph repair, all keyed by stable record
 * keys. This is the list case — one container, positional indices, a server that
 * re-renders the truth. They are different surfaces sharing a library, not one
 * behaviour written twice; when the table controller next changes materially,
 * its list half belongs here.
 *
 * **Why the drop is reverted.** SortableJS leaves the DOM in the dropped order,
 * and then Livewire morphs the server's answer over it. A Repeater's cards carry
 * no `wire:key` (morph is positional) while a Builder's blocks are keyed by
 * index — so the same dropped DOM converges in one view and flips back in the
 * other. Putting the node back where it started and letting the re-render place
 * it removes the disagreement: the server is the only thing that decides order.
 */

import Sortable from 'sortablejs';

// One pair of morph hooks per document, not one per controller: Livewire's
// hook() has no off switch, so registering inside init() stacks a fresh pair for
// every repeater on the page, every wire:navigate and every lazily loaded modal
// — and each stacked copy keeps running against a component that is gone. Same
// reasoning, and the same element-keyed registry, as wire-sortable's.
const controllers = new Map();
let morphGuardsInstalled = false;

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
};

export function wireSortableList(config = {}) {
    return {
        instance: null,
        isDragging: false,
        /** Where the dragged node has to go back to — captured before it moves. */
        origin: null,

        config: {
            // Selector for the element that holds the items, resolved inside the
            // component root. A card repeater drags the root's own children; a
            // table repeater drags `<tbody>`'s.
            container: config.container ?? null,
            // One attribute name, read two ways: as the `draggable` selector and
            // as the place an item's declared index is written. Deriving the
            // selector from the name keeps them from drifting apart.
            indexAttribute: config.indexAttribute ?? 'data-sortable-item',
            handle: config.handle ?? '[data-sortable-handle]',
            animation: config.animation ?? 150,
        },

        init() {
            this.$nextTick(() => this.bind());

            controllers.set(this.$root, this);
            installMorphGuards();
        },

        destroy() {
            controllers.delete(this.$root);
            this.instance?.destroy();
            this.instance = null;
        },

        /** The selector matching one item, built from the index attribute. */
        itemSelector() {
            return `[${this.config.indexAttribute}]`;
        },

        /** The element whose direct children are the sortable items. */
        container() {
            if (this.config.container === null) return this.$root;

            return this.$root.querySelector(this.config.container);
        },

        bind() {
            const container = this.container();
            if (!container) return;

            this.instance?.destroy();

            this.instance = new Sortable(container, {
                draggable: this.itemSelector(),
                handle: this.config.handle,
                animation: this.config.animation,
                ghostClass: 'wire-sortable-list-ghost',
                chosenClass: 'wire-sortable-list-chosen',
                dragClass: 'wire-sortable-list-drag',
                // A dragged <tr> loses its cells' widths to the clone unless the
                // fallback element carries them; forceFallback keeps one element
                // under our control in both layouts rather than two code paths.
                forceFallback: true,
                fallbackClass: 'wire-sortable-list-fallback',
                fallbackTolerance: 3,

                onChoose: (event) => this.lockWidths(event.item),
                onUnchoose: (event) => this.unlockWidths(event.item),

                onStart: (event) => {
                    this.isDragging = true;
                    document.body.classList.add('wire-sortable-list-active');

                    // The node that followed this one before the drag. Every
                    // other sibling keeps its relative order during a sort, so
                    // re-inserting before this one restores the list exactly —
                    // in both directions.
                    this.origin = { parent: event.from, next: event.item.nextElementSibling };
                },

                onEnd: (event) => {
                    this.isDragging = false;
                    document.body.classList.remove('wire-sortable-list-active');

                    this.unlockWidths(event.item);

                    if (event.oldIndex === event.newIndex) return;

                    const order = this.currentOrder(container);

                    // Put the node back before asking: see the header comment —
                    // the server's re-render is what places it, in both layouts.
                    this.revert(event.item);

                    if (order.length > 0) {
                        this.$root.dispatchEvent(
                            new CustomEvent('sorted', { detail: { order }, bubbles: false }),
                        );
                    }
                },
            });
        },

        /**
         * The items' declared indices in the order they now appear — the payload
         * `reorderRepeaterItems()` takes: old index at new position.
         */
        currentOrder(container) {
            return Array.from(container.querySelectorAll(this.itemSelector()))
                .map((el) => parseInt(el.getAttribute(this.config.indexAttribute), 10))
                .filter((index) => !Number.isNaN(index));
        },

        /**
         * Put the dragged node back where it started.
         *
         * From the captured sibling, not from `oldIndex`. Indexing the *current*
         * children by the old index only works when the item moved forwards: drag
         * the last row of `[A, B, C]` to the front and the list reads `[C, A, B]`,
         * where `children[2]` is `B` — so inserting before it yields `[A, C, B]`,
         * a third arrangement that was never anybody's intent. It converged
         * anyway, because the server's re-render is the authority, but the user
         * saw a wrong list flash past on the way.
         */
        revert(item) {
            if (! this.origin) return;

            const { parent, next } = this.origin;
            this.origin = null;

            // Defensive, and it has fired: a morph between the grab and the drop
            // can replace the sibling, leaving a node that is no longer a child
            // of the container. Reverting is cosmetic — the server's re-render is
            // what places the row — so a stale reference must fall back to the
            // end of the list, never throw and take the `sorted` dispatch (and
            // with it the whole reorder) down with it.
            if (next && next.parentNode !== parent) {
                parent.appendChild(item);

                return;
            }

            parent.insertBefore(item, next);
        },

        /**
         * Freeze a table row's cell widths for the duration of the drag. A
         * detached `<tr>` has no table to size its cells against, so without this
         * the dragged row collapses to its text width. A no-op off a table.
         */
        lockWidths(item) {
            if (!item || item.tagName !== 'TR') return;

            Array.from(item.children).forEach((cell) => {
                cell.style.width = `${cell.offsetWidth}px`;
            });
        },

        unlockWidths(item) {
            if (!item || item.tagName !== 'TR') return;

            Array.from(item.children).forEach((cell) => {
                cell.style.width = '';
            });
        },

        /** Never morph over a drag in progress — it would move the node twice. */
        onMorphUpdating(el, skip) {
            if (!this.isDragging) return;
            if (!this.$root.contains(el)) return;

            skip();
        },

        /**
         * Rebind if the morph replaced the container element itself. Livewire
         * patches children in place, so this is normally a no-op; a container
         * that arrived with a `wire:key` change is the case it catches.
         */
        onMorphUpdated(el) {
            if (this.isDragging || !this.$root.contains(el)) return;

            if (this.instance && this.instance.el?.isConnected) return;

            this.$nextTick(() => this.bind());
        },
    };
}

// The `registered` guard is load-bearing: `@wireStackScripts` and a per-surface
// partial can both emit the same src, and the browser executes it twice.
let registered = false;

const registerWireSortableList = () => {
    if (registered || !window.Alpine) return;
    registered = true;

    window.Alpine.data('wireSortableList', wireSortableList);
};

if (window.Alpine) registerWireSortableList();
else document.addEventListener('alpine:init', registerWireSortableList);

/**
 * The collapse state of a Repeater's rows, and of a Builder's blocks.
 *
 * One controller for both, because it is the same question in both fields:
 * *is item N folded away right now*, with the field's expansion policy —
 * `collapsed()`, `expandFirst()`, `expandLast()` — answering for every item
 * nobody has touched.
 *
 * Keying by index in an object is load-bearing. An earlier version held an
 * array sized to the item count, which meant adding or removing a row changed
 * the `x-data` attribute text — so Alpine re-initialised the component and
 * every row sprang back to its default. An object keyed by index leaves the
 * attribute alone.
 *
 * The same hazard is why the *default* arrives per call rather than as config.
 * "Only the last item is open" depends on how many items there are, and baking
 * that count into `x-data` would reintroduce exactly the churn above. The server
 * already knows the count when it renders a row, so it resolves the policy per
 * item and passes the answer in that row's own markup — inside the loop, where
 * changing text is what a re-render is supposed to do.
 */
const wireCollapsibleItems = (config = {}) => ({
    collapsed: {},
    collapsedByDefault: config.collapsedByDefault ?? false,

    /**
     * @param {number} index
     * @param {boolean|null} fallback the server's answer for an untouched item
     */
    isCollapsed(index, fallback = null) {
        return this.collapsed[index] ?? (fallback ?? this.collapsedByDefault)
    },

    toggleCollapse(index, fallback = null) {
        this.collapsed[index] = ! this.isCollapsed(index, fallback)
    },

    /**
     * Whether the last bulk action folded everything — what the header toggle
     * reads to decide which way it points next. Seeded by the view from the
     * field's policy, so a `collapsed()` repeater's toggle offers "expand all"
     * first rather than repeating what is already true.
     */
    allCollapsed: config.allCollapsed ?? false,

    /**
     * Fold or unfold every item at once.
     *
     * The count comes from the DOM rather than from config, and that is safe
     * *here* where it would not be in `isCollapsed()`: this runs once, from a
     * click, so it reads the live count instead of being a reactive effect that
     * would never re-run. Items announce themselves with `data-collapsible-item`
     * — the one convention this controller asks of a view.
     */
    setAllCollapsed(state) {
        this.$root.querySelectorAll('[data-collapsible-item]').forEach((el) => {
            const index = parseInt(el.getAttribute('data-collapsible-item'), 10)

            if (! Number.isNaN(index)) this.collapsed[index] = state
        })

        this.allCollapsed = state
    },
})

export default wireCollapsibleItems

/**
 * The collapse state of a Repeater's rows, and of a Builder's blocks.
 *
 * One controller for both, because it is the same question in both fields:
 * *is item N folded away right now*, with the field's `collapsed()` default
 * answering for every item nobody has touched.
 *
 * Keying by index in an object is load-bearing. An earlier version held an
 * array sized to the item count, which meant adding or removing a row changed
 * the `x-data` attribute text — so Alpine re-initialised the component and
 * every row sprang back to its default. An object keyed by index leaves the
 * attribute alone.
 */
const wireCollapsibleItems = (config = {}) => ({
    collapsed: {},
    collapsedByDefault: config.collapsedByDefault ?? false,

    isCollapsed(index) {
        return this.collapsed[index] ?? this.collapsedByDefault
    },

    toggleCollapse(index) {
        this.collapsed[index] = ! this.isCollapsed(index)
    },
})

export default wireCollapsibleItems

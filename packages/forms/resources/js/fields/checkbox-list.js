/**
 * CheckboxList's controller: the search box, and the two bulk toggles.
 *
 * Selecting everything writes the option values straight to the field's state
 * rather than clicking N boxes — one Livewire update instead of one per option,
 * which is what keeps "select all" usable on a long list.
 *
 * The values are resolved in PHP; the browser never derives them from the DOM,
 * so a filtered list cannot make "select all" mean "select what is visible".
 */
const wireCheckboxList = (config = {}) => ({
    statePath: config.statePath ?? '',
    values: config.values ?? [],

    search: '',

    selectAll() {
        this.$wire.set(this.statePath, this.values)
    },

    deselectAll() {
        this.$wire.set(this.statePath, [])
    },
})

export default wireCheckboxList

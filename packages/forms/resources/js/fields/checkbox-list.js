/**
 * CheckboxList's controller: the search box, the two bulk toggles, and the chips
 * that say what is currently chosen.
 *
 * Selecting everything writes the option values straight to the field's state
 * rather than clicking N boxes — one Livewire update instead of one per option,
 * which is what keeps "select all" usable on a long list.
 *
 * **The bulk toggles act on what the search left.** They used to act on every
 * option regardless, on the grounds that the values come from PHP and the
 * browser should never derive them from the DOM — which is still true, and is
 * why `matching` filters the PHP list rather than reading the rendered rows. But
 * the behaviour that grew out of it was wrong: filtering a permission list to
 * `invoices` and pressing "Select all" granted every permission in the system,
 * and pressing "Deselect all" revoked them. A control that acts outside what it
 * is pointed at is the oldest way a bulk action becomes an accident.
 *
 * So both toggles work against the matches, and both leave the rest of the
 * selection alone: select adds, deselect removes. With no search typed the
 * matches *are* every option, so an unfiltered list behaves exactly as it did —
 * one code path, no special case.
 *
 * `state` is entangled and comes in from the x-data expression, because
 * `@entangle` compiles to an Alpine magic and magics are in scope only there.
 * The chips read it rather than the checkboxes: the DOM only holds the options
 * near the scroll position and, with a search typed, only the matches — so
 * counting ticked boxes would under-report exactly when the chips are worth
 * having.
 */
const wireCheckboxList = (config = {}) => ({
    statePath: config.statePath ?? '',
    values: config.values ?? [],
    labels: config.labels ?? {},
    // Absent unless the chips were asked for; the getter treats that as nothing
    // selected rather than reaching for a binding that was never made.
    state: config.state ?? null,

    search: '',

    /**
     * The chosen options, in the order the list offers them.
     *
     * Not in the order they were ticked: a chip row that reshuffles itself as
     * somebody works down a list is harder to read than the list it summarises.
     */
    get selected() {
        const chosen = Array.isArray(this.state) ? this.state : []

        return this.values
            .filter((value) => chosen.includes(value))
            .map((value) => ({ value, label: this.labels[value] ?? value }))
    },

    /** Take one off, from the chip rather than from the row it belongs to. */
    remove(value) {
        if (! Array.isArray(this.state)) return

        this.state = this.state.filter((selected) => selected !== value)
    },

    /**
     * The option values the current search leaves standing.
     *
     * Matched on the labels, the same text and the same comparison the rows
     * filter themselves by — two rules would drift, and the one in Blade is the
     * one nobody can test. With nothing typed this is every option.
     */
    get matching() {
        const term = this.search.trim().toLowerCase()

        if (term === '') return this.values

        return this.values.filter(
            (value) => String(this.labels[value] ?? value).toLowerCase().includes(term)
        )
    },

    /** What is selected right now, whether or not the chips entangled it. */
    current() {
        const selected = this.$wire.get(this.statePath)

        return Array.isArray(selected) ? selected : []
    },

    /** Add the matches, keeping whatever is selected outside the filter. */
    selectAll() {
        const matching = this.matching
        const current = this.current()

        // Filtered from `values` rather than concatenated, so the stored order
        // is the order the options were declared in however they were added.
        this.$wire.set(
            this.statePath,
            this.values.filter((value) => current.includes(value) || matching.includes(value))
        )
    },

    /** Take the matches off, and only them. */
    deselectAll() {
        const matching = this.matching

        this.$wire.set(this.statePath, this.current().filter((value) => ! matching.includes(value)))
    },
})

export default wireCheckboxList

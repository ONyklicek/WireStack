/**
 * MorphToSelect's controller: the record list follows the type.
 *
 * Two selects over two columns — `{name}_type` and `{name}_id` — where the
 * second one's options are whichever list the first one names. The map arrives
 * fully resolved from PHP, so choosing a type costs no roundtrip.
 *
 * Changing the type clears the id. It has to: a key from the previous type is
 * still a valid-looking number, and leaving it would point the morph at a row
 * of the wrong table.
 *
 * `selectedType` comes in from the x-data expression because `$wire.entangle()`
 * only binds when it is the initial value of an Alpine property.
 */
const wireMorphToSelect = (config = {}) => ({
    selectedType: config.selectedType,
    idStatePath: config.idStatePath ?? '',
    typeOptions: config.typeOptions ?? {},

    init() {
        this.$watch('selectedType', () => {
            this.$wire.set(this.idStatePath, null)
        })
    },

    get idOptions() {
        return this.typeOptions[this.selectedType] || {}
    },
})

export default wireMorphToSelect

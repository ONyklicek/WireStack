/**
 * KeyValue's controller: an editable list of pairs over one array value.
 *
 * Every mutation replaces the array rather than editing it in place. Livewire's
 * entangled arrays are proxies, and mutating an element (`pairs[i].key = …`)
 * does not always reach the property setter — assigning a new array always
 * does, which is why each method below rebuilds one.
 *
 * `pairs` comes in from the x-data expression because `@entangle` compiles to
 * an Alpine magic, in scope only there.
 */
const wireKeyValue = (config = {}) => ({
    pairs: config.state,

    init() {
        // A field that has never been filled arrives as null; the row loop and
        // every method below need a list.
        if (! Array.isArray(this.pairs)) this.pairs = []
    },

    addPair() {
        this.pairs = [...this.pairs, { key: '', value: '' }]
    },

    removePair(index) {
        this.pairs = this.pairs.filter((_, i) => i !== index)
    },

    updateKey(index, val) {
        const updated = [...this.pairs]

        updated[index] = { ...updated[index], key: val }
        this.pairs = updated
    },

    updateValue(index, val) {
        const updated = [...this.pairs]

        updated[index] = { ...updated[index], value: val }
        this.pairs = updated
    },
})

export default wireKeyValue

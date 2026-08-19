/**
 * Tags input: a chip list over an array state, with a filtered suggestions panel.
 *
 * `state` comes in from the x-data expression because `@entangle` compiles to an
 * Alpine magic and magics are in scope only there.
 */
const wireTagsInput = (config = {}) => ({
    tags: config.state,
    input: '',
    suggestions: config.suggestions ?? [],
    splitKeys: config.splitKeys ?? [],
    allowNew: config.allowNew ?? true,
    allowDuplicates: config.allowDuplicates ?? false,
    maxItems: config.maxItems ?? null,
    sheetOnMobile: config.sheetOnMobile ?? false,
    sheetBreakpoint: config.sheetBreakpoint ?? null,
    focused: false,
    activeIndex: -1,
    _float: null,

    init() {
        if (! Array.isArray(this.tags)) this.tags = []

        // Teleport + Floating UI: pin the suggestions list to the input row.
        this.$watch('showDropdown', (show) => {
            if (show) {
                this.$nextTick(() => {
                    const options = { placement: 'bottom-start', offset: 8, matchWidth: true }

                    if (this.sheetOnMobile) {
                        options.sheetOnMobile = true
                        options.sheetBreakpoint = this.sheetBreakpoint
                    }

                    this._float = this.$float(this.$refs.trigger, this.$refs.panel, options)
                })
            } else if (this._float) {
                this._float()
                this._float = null
            }
        })
    },

    get filteredSuggestions() {
        if (! this.input.trim() || ! this.suggestions.length) return []
        return this.suggestions.filter((s) =>
            s.toLowerCase().includes(this.input.toLowerCase())
            && (this.allowDuplicates || ! this.tags.includes(s)),
        )
    },

    get showDropdown() {
        return this.focused && this.filteredSuggestions.length > 0
    },

    get atLimit() {
        return this.maxItems !== null && this.tags.length >= this.maxItems
    },

    addTag(value) {
        const tag = value.trim()
        if (! tag || this.atLimit) return
        if (! this.allowDuplicates && this.tags.includes(tag)) { this.input = ''; return }
        if (! this.allowNew && ! this.suggestions.includes(tag)) return
        this.tags = [...this.tags, tag]
        this.input = ''
        this.activeIndex = -1
    },

    removeTag(index) {
        this.tags = this.tags.filter((_, i) => i !== index)
    },

    onKeydown(event) {
        if (this.splitKeys.includes(event.key)) {
            event.preventDefault()
            this.activeIndex >= 0 && this.filteredSuggestions[this.activeIndex]
                ? this.addTag(this.filteredSuggestions[this.activeIndex])
                : this.addTag(this.input)
        } else if (event.key === 'Backspace' && ! this.input && this.tags.length) {
            this.removeTag(this.tags.length - 1)
        } else if (event.key === 'ArrowDown') {
            event.preventDefault()
            this.activeIndex = Math.min(this.activeIndex + 1, this.filteredSuggestions.length - 1)
        } else if (event.key === 'ArrowUp') {
            event.preventDefault()
            this.activeIndex = Math.max(this.activeIndex - 1, -1)
        } else if (event.key === 'Escape') {
            this.focused = false
        }
    },
})

export default wireTagsInput

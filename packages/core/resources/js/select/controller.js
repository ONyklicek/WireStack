/**
 * The canonical searchable-select combobox (Teleport + Floating UI).
 *
 * One shared owner for the dropdown consumed by wire-forms (`Select`,
 * `BelongsToSelect`) and five table filter surfaces, which is why it lives in
 * core's bundle rather than the forms one — the same reason the partial it backs
 * lives in `wire-core::partials.searchable-select`.
 *
 * `selected` comes in from the x-data expression: `$wire.entangle` is an Alpine
 * magic and magics are in scope only there. `statePath` is passed as config as
 * well, because `fetchRemote()` and `upsertOption()` need it as a plain string.
 */
const wireSearchableSelect = (config = {}) => ({
    open: false,
    search: '',
    multiple: config.multiple ?? false,
    remote: config.remote ?? false,
    loading: false,
    // Seeded from initialOptions in init(); avoids embedding the whole options
    // map twice in the HTML for every select instance.
    initialOptions: config.initialOptions ?? {},
    options: {},
    placeholder: config.placeholder ?? '',
    selected: config.state,
    statePath: config.statePath,
    selectId: config.selectId ?? 'searchable-select',
    sheetOnMobile: config.sheetOnMobile ?? false,
    sheetBreakpoint: config.sheetBreakpoint ?? null,
    activeIndex: -1,
    _float: null,

    init() {
        this.options = this.initialOptions

        // Teleport + Floating UI: pin the listbox to the trigger while open,
        // tearing the auto-updater down on close.
        this.$watch('open', (open) => {
            if (open) {
                this.$nextTick(() => {
                    const options = { placement: 'bottom-start', offset: 4, matchWidth: true }

                    if (this.sheetOnMobile) {
                        options.sheetOnMobile = true
                        options.sheetBreakpoint = this.sheetBreakpoint
                    }

                    this._float = this.$float(this.$refs.trigger, this.$refs.panel, options)
                    this.$refs.searchInput?.focus()
                })
            } else if (this._float) {
                this._float()
                this._float = null
            }
        })

        // Remote search: ask the server for matches as the term changes, always
        // keeping the initial seed (which carries the current selection's label)
        // so the trigger stays readable.
        if (this.remote) {
            this.$watch('search', (value) => this.fetchRemote(value))
        }
    },

    async fetchRemote(search) {
        this.loading = true
        this.activeIndex = -1
        try {
            const results = await this.$wire.searchSelectOptions(this.statePath, search ?? '')
            this.options = { ...this.initialOptions, ...(results ?? {}) }
        } finally {
            this.loading = false
        }
    },

    get filteredOptions() {
        // The server already narrowed remote results; never re-filter locally.
        if (this.remote) return this.options
        if (! this.search) return this.options
        const s = this.search.toLowerCase()
        return Object.fromEntries(
            Object.entries(this.options).filter(([k, v]) => String(v).toLowerCase().includes(s)),
        )
    },

    get filteredKeys() {
        return Object.keys(this.filteredOptions)
    },

    isSelected(value) {
        if (this.multiple) {
            return Array.isArray(this.selected) && this.selected.map(String).includes(String(value))
        }
        return this.selected !== null && this.selected !== undefined && String(this.selected) === String(value)
    },

    get selectedLabel() {
        if (this.multiple) {
            const list = Array.isArray(this.selected) ? this.selected : []
            return list.map((v) => this.options[v]).filter(Boolean).join(', ')
        }
        return this.options[this.selected] || ''
    },

    /**
     * Merge a freshly created/edited option into both option maps so the new
     * choice is selectable — and its label readable on the trigger — without a
     * page refresh (Alpine never re-reads render-time seeds on morph).
     */
    upsertOption(detail) {
        if (! detail || detail.statePath !== this.statePath || detail.value === null || detail.value === undefined) return
        this.initialOptions = { ...this.initialOptions, [detail.value]: detail.label }
        this.options = { ...this.options, [detail.value]: detail.label }
    },

    /**
     * Persist a chosen remote option's label into the seed map so it survives the
     * search reset that follows a selection (remote results are transient —
     * fetchRemote rebuilds `options` from `initialOptions` plus the current term's
     * matches, and an empty term returns nothing). Mirrors upsertOption and the
     * server-side getSelectedOptionLabels seed so selectedLabel/isSelected stay
     * readable without a page refresh.
     */
    persistSelectedOption(value) {
        if (value === null || value === undefined) return
        const label = this.options[value]
        if (label === undefined) return
        this.initialOptions = { ...this.initialOptions, [value]: label }
    },

    select(value) {
        if (this.multiple) {
            let list = Array.isArray(this.selected) ? [...this.selected] : []
            const idx = list.map(String).indexOf(String(value))
            if (idx === -1) {
                list.push(value)
                this.persistSelectedOption(value)
            } else {
                list.splice(idx, 1)
            }
            this.selected = list
            return
        }
        this.persistSelectedOption(value)
        this.selected = value
        this.open = false
        this.search = ''
        this.activeIndex = -1
    },

    clear() {
        this.selected = this.multiple ? [] : null
        this.search = ''
        this.activeIndex = -1
        if (! this.multiple) {
            this.open = false
        }
    },

    onArrowDown() {
        if (! this.open) { this.open = true; return }
        if (this.activeIndex < this.filteredKeys.length - 1) this.activeIndex++
    },

    onArrowUp() {
        if (this.activeIndex > 0) this.activeIndex--
    },

    onEnter() {
        if (this.activeIndex >= 0 && this.activeIndex < this.filteredKeys.length) {
            this.select(this.filteredKeys[this.activeIndex])
        }
    },

    get activeDescendant() {
        if (this.activeIndex < 0) return null
        return this.selectId + '-option-' + this.filteredKeys[this.activeIndex]
    },
})

export default wireSearchableSelect

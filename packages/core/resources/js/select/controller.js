import { searchMatcher } from '../support/search'

/**
 * A shared panel's options, parsed once per version of its markup. Every
 * trigger of a table column reads the same <template>; a morph that changes the
 * list changes the attribute, and the next reader parses it again.
 */
const panelData = new WeakMap()

const readPanel = (template) => {
    const raw = template.dataset.options ?? '[]'
    const disabled = template.dataset.disabledValues ?? '[]'
    const cached = panelData.get(template)
    if (cached && cached.raw === raw && cached.disabled === disabled) return cached

    const parsed = { raw, disabled, pairs: JSON.parse(raw), disabledValues: JSON.parse(disabled).map(String) }
    panelData.set(template, parsed)

    return parsed
}

const sameSelection = (a, b) => {
    if (Array.isArray(a) || Array.isArray(b)) {
        return JSON.stringify((a ?? []).map(String)) === JSON.stringify((b ?? []).map(String))
    }

    return (a ?? null) === (b ?? null)
}

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
 *
 * Two configurations serve an editable table cell, which writes through its own
 * component rather than a Livewire property:
 *
 *  - `commit` — called with the new selection (a single select as it is picked,
 *    a multiple one as its list closes, if it changed). `state` is then the
 *    enclosing component's value, and the selection follows it whenever it
 *    moves — a rollback, a conflict, a poll.
 *  - `panel` — the id of a list drawn once for the whole column. It is cloned
 *    into <body> the first time this instance opens, under this instance's
 *    scope, and its options are read from it rather than from `initialOptions`.
 */
const wireSearchableSelect = (config = {}) => ({
    open: false,
    search: '',
    multiple: config.multiple ?? false,
    remote: config.remote ?? false,
    loading: false,
    // The render-time seed arrives as [value, label] pairs — a JS object would
    // list integer-like keys in ascending order, whatever order the server wrote
    // them in. init() splits it into a label map for lookups and `order` for
    // listing, so neither has to rebuild the other on every keystroke.
    initialOptions: {},
    options: {},
    order: [],
    // Remote search: the values the server answered for the current term, in
    // its order. `options` stays the label map (seed + every result), so the
    // trigger keeps reading a selection the term no longer matches, while the
    // list shows what was searched for — never the seed above it. Null while
    // nothing is typed: the seed (a `preload()`) is the list then.
    remoteKeys: null,
    disabledValues: (config.disabledValues ?? []).map(String),
    placeholder: config.placeholder ?? '',
    selected: config.state,
    statePath: config.statePath,
    panelId: config.panel ?? null,
    _panel: null,
    _panelRaw: null,
    _opened: null,
    selectId: config.selectId ?? 'searchable-select',
    sheetOnMobile: config.sheetOnMobile ?? false,
    sheetBreakpoint: config.sheetBreakpoint ?? null,
    // ->touchOnMobile(): below touchBreakpoint the list opens as a full-height
    // touch sheet instead of the floating panel. `touchNow` is decided as the
    // list opens — the one moment the viewport's width is the answer.
    touch: config.touch ?? false,
    touchBreakpoint: config.touchBreakpoint ?? 639.98,
    touchNow: false,
    activeIndex: -1,
    _float: null,

    init() {
        const pairs = Array.isArray(config.initialOptions) ? config.initialOptions : Object.entries(config.initialOptions ?? {})
        this.seed(pairs)

        if (this.panelId) {
            // The trigger's own id, for aria-activedescendant: the shared list
            // is the same markup for every row, so it reads it from here.
            this.selectId = this.$el.querySelector('[aria-haspopup=listbox]')?.id ?? this.selectId
            // A created option is announced for the column, not for one cell.
            this.statePath = this.panelId
            this.loadPanelOptions()
        }

        if (config.commit) {
            // The enclosing component owns the value; follow it, whoever moved it.
            this.$watch(config.stateKey, (next) => {
                if (! sameSelection(next, this.selected)) this.selected = next
            })
        }

        // Teleport + Floating UI: pin the listbox to the trigger while open,
        // tearing the auto-updater down on close.
        this.$watch('open', (open) => {
            if (config.commit && this.multiple) {
                if (open) {
                    this._opened = this.selected
                } else if (! sameSelection(this._opened, this.selected)) {
                    this.commitSelection(this.selected)
                }
            }

            if (open && this.panelId) this.mountPanel()

            if (open) {
                this.touchNow = this.touch && window.matchMedia(`(max-width: ${this.touchBreakpoint}px)`).matches
                // The touch sheet is fixed full-height: nothing to anchor, and
                // no focus into the search — a keyboard sliding up over a list
                // nobody asked to type into is the thing this sheet avoids.
                if (this.touchNow) return
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

    /**
     * Hand a selection to the enclosing component, then show what it holds.
     * A cell that refuses the change (its own write is still out) keeps the
     * value it had, and an unchanged value wakes no watcher.
     */
    commitSelection(value) {
        config.commit(value)

        const held = window.Alpine.evaluate(this.$el, config.stateKey)
        if (! sameSelection(held, this.selected)) this.selected = held
    },

    seed(pairs) {
        this.order = pairs.map(([value]) => String(value))
        this.initialOptions = Object.fromEntries(pairs)
        this.options = this.initialOptions
    },

    /** The column's shared list, looked up inside this component only: two tables may share a column name. */
    panelTemplate() {
        const root = this.$el.closest('[wire\\:id]') ?? document
        const name = this.panelId.replace(/["\\]/g, '\\$&')

        return root.querySelector(`template[data-select-panel="${name}"]`)
    },

    /** Read the shared panel's options — again only when its markup changed. */
    loadPanelOptions() {
        const template = this.panelTemplate()
        if (! template) return

        const data = readPanel(template)
        if (this._panelRaw === data.raw + data.disabled) return
        this._panelRaw = data.raw + data.disabled

        // Options a create brought in stay until the list itself has them.
        const extra = Object.entries(this.options).filter(([value]) => ! data.pairs.some(([v]) => String(v) === value))
        this.seed([...data.pairs, ...extra])
        this.disabledValues = data.disabledValues
    },

    /**
     * Clone the shared list into <body> under this instance's scope — what
     * x-teleport does for a list of one's own, step for step.
     */
    mountPanel() {
        this.loadPanelOptions()
        if (this._panel?.isConnected) return

        const template = this.panelTemplate()
        const clone = template?.content?.firstElementChild?.cloneNode(true)
        if (! clone) return

        const anchor = this.$el.querySelector('[aria-haspopup=listbox]') ?? this.$el
        clone._x_teleportBack = anchor
        clone.setAttribute('data-teleport-target', true)
        // The scope itself: _x_teleportBack only leads x-ref, $clickedInside and
        // Livewire's $wire back; reading a name walks the data stack, which
        // this hands over exactly as x-teleport does.
        window.Alpine.addScopeToNode(clone, {}, anchor)
        this._panel = clone
        window.Alpine.mutateDom(() => {
            document.body.appendChild(clone)
            window.Alpine.initTree(clone)
        })
    },

    destroy() {
        if (! this._panel) return
        window.Alpine.destroyTree?.(this._panel)
        this._panel.remove()
        this._panel = null
    },

    async fetchRemote(search) {
        this.loading = true
        this.activeIndex = -1
        try {
            const results = await this.$wire.searchSelectOptions(this.statePath, search ?? '') ?? {}
            // An answer for a term already typed over would list the wrong matches.
            if ((search ?? '') !== this.search) return
            this.options = { ...this.initialOptions, ...results }
            this.remoteKeys = this.search ? Object.keys(results) : null
        } finally {
            this.loading = false
        }
    },

    /**
     * The options in display order as [value, label] pairs: the seed's order
     * first, then anything only a remote search or a created option brought in.
     */
    get orderedOptions() {
        const seen = new Set(this.order)
        const keys = this.order.filter((value) => value in this.options)
        for (const value of Object.keys(this.options)) {
            if (! seen.has(value)) keys.push(value)
        }
        return keys.map((value) => [value, this.options[value]])
    },

    get filteredOptions() {
        // The server already narrowed remote results; never re-filter locally.
        // Until the first answer arrives the list is empty (and loading), not the seed.
        if (this.remote && this.search) {
            return (this.remoteKeys ?? []).filter((value) => value in this.options).map((value) => [value, this.options[value]])
        }
        if (this.remote || ! this.search) return this.orderedOptions
        const matches = searchMatcher(this.search)
        return this.orderedOptions.filter(([, label]) => matches(label))
    },

    get filteredKeys() {
        return this.filteredOptions.map(([value]) => value)
    },

    isOptionDisabled(value) {
        return this.disabledValues.includes(String(value))
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
        if (! this.order.includes(String(detail.value))) this.order = [...this.order, String(detail.value)]
        this.initialOptions = { ...this.initialOptions, [detail.value]: detail.label }
        this.options = { ...this.options, [detail.value]: detail.label }
        if (this.remoteKeys !== null && ! this.remoteKeys.includes(String(detail.value))) {
            this.remoteKeys = [...this.remoteKeys, String(detail.value)]
        }
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
        if (this.isOptionDisabled(value)) return
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
        const changed = ! sameSelection(this.selected, value)
        this.persistSelectedOption(value)
        this.selected = value
        this.open = false
        this.search = ''
        this.activeIndex = -1
        if (changed) this.commitSelection(value)
    },

    clear() {
        const changed = ! sameSelection(this.selected, this.multiple ? [] : null)
        this.selected = this.multiple ? [] : null
        this.search = ''
        this.activeIndex = -1
        if (! this.multiple) {
            this.open = false
            if (changed) this.commitSelection(null)
        }
    },

    onArrowDown() {
        if (! this.open) { this.open = true; return }
        this.moveActive(1)
    },

    onArrowUp() {
        this.moveActive(-1)
    },

    // Steps over disabled options, and stays put when nothing enabled lies that way.
    moveActive(step) {
        const keys = this.filteredKeys
        for (let i = this.activeIndex + step; i >= 0 && i < keys.length; i += step) {
            if (! this.isOptionDisabled(keys[i])) {
                this.activeIndex = i
                return
            }
        }
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

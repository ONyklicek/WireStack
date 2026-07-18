import { computePosition, autoUpdate, flip, shift, offset, size } from '@floating-ui/dom'

/**
 * Canonical "Teleport + Floating UI" primitive for every floating surface in the
 * project (action menus, table toolbars, form select/date/tag panels, widgets).
 *
 * Panels are teleported to <body> so a parent's overflow (table horizontal
 * scroll, modal clipping) can never cut them off, then pinned to their trigger
 * with Floating UI. autoUpdate keeps them aligned through scroll/resize/layout
 * shifts until they close.
 */

/**
 * How far a teleported panel sits above the surface that owns its trigger.
 * Deliberately +1, not +10: it must clear its own modal without ever reaching
 * the next modal depth (ModalStack::Z_INDEX_STEP), so a modal opened on top of
 * an open panel still covers it.
 */
const LAYER_OFFSET = 1

/**
 * The z-index a panel needs to clear the surface its `reference` lives in, or
 * null when the trigger sits on the page itself (no explicit layer → the view's
 * own `z-50` class is right).
 *
 * Teleporting to <body> is what lets a panel escape an ancestor's overflow, but
 * it also means the panel competes in the ROOT stacking context rather than its
 * trigger's local one — the wrapper the panel lands in is `position: static`, so
 * it establishes no stacking context of its own. A panel inside a stacked action
 * modal therefore renders *behind* it: the panel's `z-50` class loses to the
 * modal's `ModalStack::zIndexForDepth()` inline z-index (60 at depth 1).
 *
 * Takes the OUTERMOST explicit z-index on the trigger's ancestor chain, not the
 * nearest: the nearest match for a trigger inside a modal's sticky header would
 * be that header's `z-10`, which would pin the panel under the modal itself.
 * Only the root-level layer is comparable to where the panel actually lands.
 */
const layerAbove = (reference) => {
    let layer = null

    for (let el = reference.parentElement; el && el !== document.body; el = el.parentElement) {
        const z = parseInt(getComputedStyle(el).zIndex, 10)

        if (! Number.isNaN(z)) {
            layer = z
        }
    }

    return layer === null ? null : layer + LAYER_OFFSET
}

/**
 * Position `floating` next to `reference` and keep it there until cleanup.
 *
 * When `sheetOnMobile` is set the panel becomes a viewport-pinned bottom sheet
 * below `sheetBreakpoint` (default 640px): Floating UI positioning is skipped so
 * the panel's own `max-sm:` sheet classes (fixed, bottom-0, full width) take
 * over, and it flips back to a trigger-anchored floating panel from sm up. The
 * mode is re-evaluated when the viewport crosses the breakpoint.
 *
 * @param {Element} reference  the trigger element
 * @param {Element} floating   the panel element (already visible)
 * @param {{placement?: string, offset?: number, matchWidth?: boolean, sheetOnMobile?: boolean, sheetBreakpoint?: number}} config
 * @returns {() => void} cleanup function that stops the auto-updater
 */
export const floatingAnchor = (reference, floating, config = {}) => {
    if (! reference || ! floating) {
        return () => {}
    }

    const placement = config.placement || 'bottom-end'
    const gap = config.offset ?? 6
    const matchWidth = config.matchWidth ?? false
    const sheetBreakpoint = config.sheetBreakpoint ?? 640
    const sheetQuery = config.sheetOnMobile
        ? window.matchMedia(`(max-width: ${sheetBreakpoint - 0.02}px)`)
        : null

    // Capture any design-intended max-height (e.g. a `max-h-80` class) before we
    // write inline styles, so the viewport-aware cap below only ever *shrinks*
    // the panel and never overrides a smaller intentional cap.
    const naturalMax = parseFloat(getComputedStyle(floating).maxHeight)
    const cappedMax = Number.isNaN(naturalMax) ? Infinity : naturalMax

    // Likewise capture the view's own layer (its `z-50` class) before any inline
    // write, so the resolved layer below can only ever *raise* the panel. A
    // trigger on the page can sit inside a low-z ancestor (a table's sticky
    // toolbar at `z-10`); without this floor the panel would drop from 50 to 11
    // and slide under unrelated page chrome.
    const naturalZ = parseInt(getComputedStyle(floating).zIndex, 10)
    const floorZ = Number.isNaN(naturalZ) ? -Infinity : naturalZ

    // `size` runs after flip/shift, so `availableHeight` is the room left on the
    // chosen side. Capping to it (with overflow) means a tall panel — a calendar,
    // a long option list — scrolls inside itself on a short (e.g. landscape phone)
    // viewport instead of spilling off-screen. `matchWidth` panels also grow to
    // their trigger's width here.
    const middleware = [
        offset(gap),
        flip(),
        shift({ padding: 8 }),
        size({
            padding: 8,
            apply({ availableHeight, rects, elements }) {
                Object.assign(elements.floating.style, {
                    maxHeight: `${Math.round(Math.min(availableHeight, cappedMax))}px`,
                    overflowY: 'auto',
                })
                if (matchWidth) {
                    elements.floating.style.minWidth = `${rects.reference.width}px`
                }
            },
        }),
    ]

    // The layer this panel must clear, resolved when it opens (see layerAbove).
    let panelZ = null

    const reposition = () => {
        // A Livewire morph can detach/replace the trigger; positioning against a
        // node that is no longer in the document collapses to (0,0) and throws the
        // panel into the top-left corner. Wait until both ends are reconnected.
        if (! reference.isConnected || ! floating.isConnected) {
            return
        }

        computePosition(reference, floating, { placement, middleware }).then(({ x, y }) => {
            Object.assign(floating.style, { left: `${x}px`, top: `${y}px` })
            // Re-assert the layer here, not just on open: a morph strips the whole
            // inline style attribute (it is absent from the server HTML), which
            // would drop the panel back to its `z-50` class and behind its modal.
            if (panelZ !== null) {
                floating.style.zIndex = `${panelZ}`
            }
        })
    }

    let stopAutoUpdate = null
    let observer = null

    // Below the sheet breakpoint the panel is a CSS bottom sheet (max-sm: classes
    // in the view), so Floating UI must not run — clear any inline positioning it
    // left behind so those classes win.
    const isSheet = () => !! sheetQuery && sheetQuery.matches

    const startFloating = () => {
        if (isSheet()) {
            // A bottom sheet is viewport-pinned by its own `max-sm:` classes, so
            // hand every inline style back — including the layer, which the sheet's
            // own z-index class owns.
            Object.assign(floating.style, { position: '', top: '', left: '', maxHeight: '', overflowY: '', minWidth: '', zIndex: '' })
            panelZ = null

            return
        }

        // Float above any stacking context now that we live on <body>.
        const resolved = layerAbove(reference)
        panelZ = (resolved !== null && resolved > floorZ) ? resolved : null
        Object.assign(floating.style, { position: 'absolute', top: '0', left: '0' })

        if (panelZ !== null) {
            floating.style.zIndex = `${panelZ}`
        }

        stopAutoUpdate = autoUpdate(reference, floating, reposition)

        // Livewire DOM morphs strip the inline left/top Floating UI writes (they
        // are absent from the server HTML), so any re-render while the panel is
        // open drops it into the top-left corner until the next scroll/resize.
        // Re-pin on every morph that touches the panel, ignoring our own style
        // writes to avoid a loop.
        observer = new MutationObserver((mutations) => {
            const onlyOurStyle = mutations.every(
                (m) => m.target === floating && m.type === 'attributes' && m.attributeName === 'style',
            )

            if (! onlyOurStyle) {
                reposition()
            }
        })

        observer.observe(floating, { childList: true, subtree: true, attributes: true })
    }

    const stopFloating = () => {
        if (stopAutoUpdate) {
            stopAutoUpdate()
            stopAutoUpdate = null
        }
        if (observer) {
            observer.disconnect()
            observer = null
        }
    }

    // Re-evaluate the mode when the viewport crosses the sheet breakpoint.
    const onBreakpointChange = () => {
        stopFloating()
        startFloating()
    }
    sheetQuery?.addEventListener('change', onBreakpointChange)

    startFloating()

    return () => {
        stopFloating()
        sheetQuery?.removeEventListener('change', onBreakpointChange)
    }
}

/**
 * Is `target` inside `container`, following teleports?
 *
 * Every floating panel here is teleported to <body>, so a panel opened from
 * inside another panel becomes a DOM *sibling* of it rather than a descendant.
 * A plain `contains()` outside-check therefore reads a click in the inner panel
 * as "outside" the outer one and closes it — picking an option in a select
 * filter used to shut the whole table Filters panel before the choice applied.
 *
 * Walk the ancestor chain, hopping from a teleported subtree back to the
 * `<template x-teleport>` it was cloned from (Alpine links the clone via
 * `_x_teleportBack`), so containment follows the nesting the author wrote
 * rather than the flattened DOM.
 */
const containsThroughTeleports = (container, target) => {
    if (!container || !target) return false

    let node = target
    while (node) {
        if (node === container) return true
        node = node._x_teleportBack ?? node.parentElement
    }

    return false
}

/**
 * Self-contained dropdown for simple owner menus (ActionGroup, x-wire::dropdown,
 * toolbar buttons). Expects x-ref="trigger" and x-ref="panel" in scope.
 */
const wireDropdown = (config = {}, items = null) => ({
    open: false,
    _cleanup: null,

    // Lazy menu (ActionGroup::lazyMenu()): the item spec is rendered client-side on
    // first open, so the row ships no menu markup. null when the group renders eagerly.
    items,
    _wire: null,

    init() {
        // Capture $wire while the component is in its live DOM position, before the
        // panel teleports to <body> where $wire may not resolve. Lazy menu clicks go
        // through this reference, so they work from the teleported panel.
        this._wire = this.$wire ?? null
    },

    // Invoke a lazy menu item's action: $wire[method](...args). The method + args
    // are pre-split server-side, so nothing is evaluated from a string (CSP-safe).
    runAction(item) {
        if (item && item.method && this._wire && typeof this._wire[item.method] === 'function') {
            this._wire[item.method](...(item.args || []))
        }
    },

    toggle() {
        this.open ? this.close() : this.show()
    },

    show() {
        this.open = true
        this.$nextTick(() => {
            this._cleanup = floatingAnchor(this.$refs.trigger, this.$refs.panel, config)
        })
    },

    close() {
        this.open = false
        this.stop()
    },

    stop() {
        if (this._cleanup) {
            this._cleanup()
            this._cleanup = null
        }
    },

    // Alpine calls destroy() when the host element is removed (e.g. a row morph),
    // so the autoUpdate listeners never leak.
    destroy() {
        this.stop()
    },
})

/**
 * `x-sheet-dismiss="<closeExpression>"` — drag-to-dismiss for a mobile bottom
 * sheet. Placed on the sheet's grabber handle; dragging the panel down past a
 * threshold runs the expression (the component's own close). Only active below
 * the sm breakpoint (the panel is a sheet); a no-op on desktop and for anything
 * but a downward drag, so it never fights the list's own scrolling.
 */
const registerSheetDismiss = (Alpine) => {
    Alpine.directive('sheet-dismiss', (el, { expression }, { evaluateLater, cleanup }) => {
        const runClose = evaluateLater(expression)
        // The grabber is the first child of the sheet panel; drag that panel.
        const panel = () => el.parentElement
        // Sheet breakpoint (px) is injected via data-sheet-bp; default 640 (sm).
        const isMobile = () => window.matchMedia(`(max-width: ${parseFloat(el.dataset.sheetBp) || 639.98}px)`).matches

        let startY = 0
        let delta = 0
        let dragging = false

        const onStart = (e) => {
            if (! isMobile()) return
            dragging = true
            delta = 0
            startY = e.touches[0].clientY
            const p = panel()
            if (p) p.style.transition = 'none'
        }
        const onMove = (e) => {
            if (! dragging) return
            delta = Math.max(0, e.touches[0].clientY - startY)
            const p = panel()
            if (p) p.style.transform = `translateY(${delta}px)`
        }
        const onEnd = () => {
            if (! dragging) return
            dragging = false
            const p = panel()
            if (p) {
                p.style.transition = ''
                p.style.transform = ''
            }
            // Past ~90px of pull-down → dismiss; otherwise it snaps back.
            if (delta > 90) runClose(() => {})
        }

        el.addEventListener('touchstart', onStart, { passive: true })
        el.addEventListener('touchmove', onMove, { passive: true })
        el.addEventListener('touchend', onEnd)
        cleanup(() => {
            el.removeEventListener('touchstart', onStart)
            el.removeEventListener('touchmove', onMove)
            el.removeEventListener('touchend', onEnd)
        })
    })
}

/**
 * `x-focus-trap="<openExpression>"` — accessibility for a mobile bottom sheet.
 * While the sheet is open below its breakpoint it behaves like a modal dialog:
 * focus moves inside, Tab cycles within the panel (both directions), the panel
 * is announced with aria-modal, and focus returns to the trigger on close. On
 * desktop (floating panel) it is a no-op, so normal dropdown tabbing is intact.
 */
const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

const registerFocusTrap = (Alpine) => {
    Alpine.directive('focus-trap', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const getOpen = evaluateLater(expression)
        const isMobile = () => window.matchMedia(`(max-width: ${parseFloat(el.dataset.sheetBp) || 639.98}px)`).matches
        const focusables = () => [...el.querySelectorAll(FOCUSABLE)].filter((n) => n.offsetParent !== null)

        let restoreTo = null
        let onKeydown = null
        let active = false

        const activate = () => {
            if (active || ! isMobile()) return
            active = true
            restoreTo = document.activeElement
            el.setAttribute('aria-modal', 'true')
            const items = focusables()
            ;(items[0] ?? el).focus({ preventScroll: true })

            onKeydown = (e) => {
                if (e.key !== 'Tab') return
                const list = focusables()
                if (list.length === 0) { e.preventDefault(); return }
                const first = list[0]
                const last = list[list.length - 1]
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() } else if (! e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
            }
            el.addEventListener('keydown', onKeydown)
        }

        const deactivate = () => {
            if (! active) return
            active = false
            el.removeAttribute('aria-modal')
            if (onKeydown) { el.removeEventListener('keydown', onKeydown); onKeydown = null }
            // Defer a frame so the restore wins over the browser resetting focus
            // to <body> as x-show hides the (still-focused) panel.
            const target = restoreTo
            restoreTo = null
            if (target && typeof target.focus === 'function') {
                requestAnimationFrame(() => target.focus({ preventScroll: true }))
            }
        }

        effect(() => {
            getOpen((open) => {
                // Wait a frame so x-show has made the panel focusable before we move focus in.
                open ? requestAnimationFrame(activate) : deactivate()
            })
        })

        cleanup(deactivate)
    })
}

/**
 * wireTabs — client-side state for the standalone <x-wire::tabs> tag. Panels
 * self-register their label on init (in DOM order) and the tablist renders a
 * button per registered tab; `active` drives which panel is shown.
 */
const wireTabs = (initial = 0) => ({
    tabs: [],
    active: initial,
    registerTab(label) {
        this.tabs.push(label)

        return this.tabs.length - 1
    },
})

/**
 * wireWizard — client-side state for the standalone <x-wire::wizard> tag. Steps
 * self-register on init; the indicator and Back/Next controls read `current`.
 */
const wireWizard = (initial = 0) => ({
    steps: [],
    current: initial,
    registerStep(label) {
        this.steps.push(label)

        return this.steps.length - 1
    },
    get isFirst() {
        return this.current === 0
    },
    get isLast() {
        return this.current >= this.steps.length - 1
    },
    next() {
        if (! this.isLast) this.current++
    },
    prev() {
        if (! this.isFirst) this.current--
    },
})

/*
 * wireEditableCell — canonical inline-editable table cell.
 *
 * updateTableCell() calls skipRender() so the table is NOT re-rendered (a DOM
 * morph would destroy Alpine cell state), which means every editable cell must
 * switch its own appearance optimistically and reconcile with the server:
 *
 *  - commit(next): optimistic value → $wire.updateTableCell(key, col, next, ver);
 *    on failure it rolls back to the last server-confirmed value, and on an
 *    optimistic-lock conflict it adopts the server's current value + version.
 *  - a MutationObserver on data-server-value / data-record-version reconciles
 *    the cell when polling (or any external re-render) changes the row.
 *
 * recordKey / columnName are read from data-* attributes (never interpolated
 * into this JS), so a primary key containing a quote can't break out. Text-style
 * cells layer save-on-blur/enter, escape-to-revert, dirty tracking and live
 * validation on top via config flags.
 */
const wireEditableCell = (config = {}) => ({
    value: config.value,
    serverValue: config.value,
    recordVersion: config.recordVersion ?? '0',
    // Livewire methods the cell commits/validates against. Default to the table
    // host so existing editable columns are unchanged; other surfaces (e.g. an
    // editable infolist) point these at their own host methods with the same
    // (recordKey, name, value, version) contract.
    commitMethod: config.commitMethod ?? 'updateTableCell',
    validateMethod: config.validateMethod ?? 'validateTableCell',
    recordKey: null,
    columnName: null,
    // Livewire component id of the host, used to scope sibling version syncing to
    // cells of the same table row / same panel record (see below).
    componentId: null,
    saving: false,
    error: null,
    success: false,
    focused: false,

    get dirty() {
        return this.value !== this.serverValue
    },

    parse(raw) {
        return config.parse ? config.parse(raw) : raw
    },

    messages: {},

    init() {
        // Read record identity + messages from data-* on the root here, where $el
        // is reliably the x-data element (inside event-triggered methods $el can be
        // the event target instead).
        this.recordKey = this.$el.dataset.recordKey
        this.columnName = this.$el.dataset.columnName
        this.messages = {
            error: this.messages.error,
            saveFailed: this.messages.saveFailed,
            invalid: this.messages.invalid,
        }

        if (config.liveValidation) {
            this.$watch('value', window.Alpine.debounce(() => {
                if (this.dirty) this.validate()
            }, config.debounce ?? 500))
        }

        const observer = new MutationObserver((mutations) => {
            for (const m of mutations) {
                if (m.attributeName === 'data-server-value' || m.attributeName === 'data-record-version') {
                    const next = this.parse(this.$el.dataset.serverValue)
                    if (next !== this.serverValue) {
                        this.syncFromServer(next, this.$el.dataset.recordVersion)
                    }
                }
            }
        })
        observer.observe(this.$el, { attributes: true, attributeFilter: ['data-server-value', 'data-record-version'] })
        this._observer = observer

        // Sibling version sync. Every editable cell captures the record's
        // optimistic-lock version at render time. When one cell commits, the host
        // skips re-rendering (to preserve Alpine state), so sibling cells bound to
        // the SAME record keep a now-stale version and would falsely conflict on
        // their next write — invisible in tables (one record per row) but the
        // common case for a panel (many fields of one record). On a successful
        // commit we broadcast the new version; siblings of the same host component
        // and record adopt it. A genuine external change still never dispatches
        // this event, so real cross-client conflicts are still caught.
        this.componentId = this.$el.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null
        this._onSiblingCommit = (e) => {
            const d = e.detail || {}
            if (d.componentId !== this.componentId) return
            if (String(d.recordKey) !== String(this.recordKey)) return
            if (d.column === this.columnName) return   // that was us
            if (this.saving) return                    // don't disturb an in-flight save
            if (this.focused && this.dirty) return     // nor a field being edited
            if (d.version) this.recordVersion = d.version
        }
        window.addEventListener('wire-editable-committed', this._onSiblingCommit)
    },

    destroy() {
        this._observer?.disconnect()
        window.removeEventListener('wire-editable-committed', this._onSiblingCommit)
    },

    syncFromServer(next, version) {
        if (this.saving) return                 // never stomp an in-flight save
        if (this.focused && this.dirty) return  // nor a field the user is editing
        this.value = next
        this.serverValue = next
        if (version) this.recordVersion = version
        this.error = null
    },

    onFocus() {
        this.focused = true
    },
    onBlur() {
        this.focused = false
        if (config.saveOnBlur && this.dirty) this.save()
    },
    onEnter() {
        if (config.saveOnEnter && this.dirty) this.save()
    },
    onEscape() {
        this.value = this.serverValue
        this.error = null
        this.$refs.input?.blur()
    },

    save() {
        if (this.dirty) this.commit(this.value)
    },

    async commit(next) {
        if (this.saving) return
        this.value = next                       // optimistic
        this.saving = true
        this.error = null
        try {
            const r = await this.$wire[this.commitMethod](
                this.recordKey,
                this.columnName,
                next,
                this.recordVersion,
            )
            if (r?.success === false) {
                this.value = this.serverValue   // rollback to last confirmed
                this.error = r.message || r.errors?.[0] || this.messages.error
                if (r?.conflict) {              // someone else won the race
                    this.value = this.parse(r.currentValue)
                    this.serverValue = this.value
                    this.recordVersion = r.currentVersion ?? this.recordVersion
                }
            } else {
                this.serverValue = next
                if (r?.version) this.recordVersion = r.version
                this.success = true
                setTimeout(() => { this.success = false }, 1500)
                // Tell sibling cells of the same record their optimistic-lock
                // version just advanced, so the next field edited on the same
                // record does not falsely conflict.
                if (r?.version) {
                    window.dispatchEvent(new CustomEvent('wire-editable-committed', {
                        detail: {
                            componentId: this.componentId,
                            recordKey: this.recordKey,
                            column: this.columnName,
                            version: r.version,
                        },
                    }))
                }
            }
        } catch (e) {
            this.value = this.serverValue       // rollback
            this.error = this.messages.saveFailed
        } finally {
            this.saving = false
        }
    },

    async validate() {
        try {
            const r = await this.$wire[this.validateMethod](
                this.recordKey,
                this.columnName,
                this.value,
            )
            this.error = (r && !r.valid) ? (r.errors?.[0] || this.messages.invalid) : null
        } catch (e) {}
    },
})

/**
 * Right-click context menu for a table row. Unlike wireDropdown (anchored to a
 * trigger via Floating UI), this pins a teleported, `position: fixed` panel at
 * the pointer coordinates and clamps it inside the viewport. Opened from a
 * `@contextmenu.prevent="openAt($event)"` on the row; closes on outside click,
 * Escape, or scroll.
 */
// Module-level handle to the single open context menu, so opening one (or
// right-clicking another row) always closes any other first.
let openContextMenu = null

const wireContextMenu = () => ({
    open: false,
    x: 0,
    y: 0,

    openAt(event) {
        if (openContextMenu && openContextMenu !== this) openContextMenu.close()
        openContextMenu = this
        this.x = event.clientX
        this.y = event.clientY
        this.open = true
        this.$nextTick(() => this.place())
    },

    // Position the panel at the cursor, nudged back inside the viewport so it is
    // never clipped at the right/bottom edge.
    place() {
        const panel = this.$refs.panel
        if (! panel) return
        const pad = 8
        const { width, height } = panel.getBoundingClientRect()
        let x = this.x
        let y = this.y
        if (x + width + pad > window.innerWidth) x = window.innerWidth - width - pad
        if (y + height + pad > window.innerHeight) y = window.innerHeight - height - pad
        panel.style.left = `${Math.max(pad, x)}px`
        panel.style.top = `${Math.max(pad, y)}px`
    },

    close() {
        this.open = false
        if (openContextMenu === this) openContextMenu = null
    },
})

document.addEventListener('alpine:init', () => {
    // $float(reference, panel, config) → cleanup. For components that own their
    // open-state and want Floating UI positioning on a teleported panel.
    window.Alpine.magic('float', () => floatingAnchor)

    // $clickedInside($event) → true when the event came from inside this element
    // *or* from a panel nested in it through a teleport. Guards `@click.outside`
    // on any panel that can host another floating panel:
    //     @click.outside="$clickedInside($event) || close()"
    window.Alpine.magic('clickedInside', (el) => (event) => containsThroughTeleports(el, event?.target))

    window.Alpine.data('wireDropdown', wireDropdown)
    window.Alpine.data('wireContextMenu', wireContextMenu)
    window.Alpine.data('wireTabs', wireTabs)
    window.Alpine.data('wireWizard', wireWizard)
    window.Alpine.data('wireEditableCell', wireEditableCell)
    registerSheetDismiss(window.Alpine)
    registerFocusTrap(window.Alpine)
})

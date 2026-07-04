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

    const reposition = () => {
        // A Livewire morph can detach/replace the trigger; positioning against a
        // node that is no longer in the document collapses to (0,0) and throws the
        // panel into the top-left corner. Wait until both ends are reconnected.
        if (! reference.isConnected || ! floating.isConnected) {
            return
        }

        computePosition(reference, floating, { placement, middleware }).then(({ x, y }) => {
            Object.assign(floating.style, { left: `${x}px`, top: `${y}px` })
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
            Object.assign(floating.style, { position: '', top: '', left: '', maxHeight: '', overflowY: '', minWidth: '' })

            return
        }

        // Float above any stacking context now that we live on <body>.
        Object.assign(floating.style, { position: 'absolute', top: '0', left: '0' })
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
 * Self-contained dropdown for simple owner menus (ActionGroup, x-wire::dropdown,
 * toolbar buttons). Expects x-ref="trigger" and x-ref="panel" in scope.
 */
const wireDropdown = (config = {}) => ({
    open: false,
    _cleanup: null,

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

document.addEventListener('alpine:init', () => {
    // $float(reference, panel, config) → cleanup. For components that own their
    // open-state and want Floating UI positioning on a teleported panel.
    window.Alpine.magic('float', () => floatingAnchor)

    window.Alpine.data('wireDropdown', wireDropdown)
    window.Alpine.data('wireTabs', wireTabs)
    window.Alpine.data('wireWizard', wireWizard)
    registerSheetDismiss(window.Alpine)
    registerFocusTrap(window.Alpine)
})

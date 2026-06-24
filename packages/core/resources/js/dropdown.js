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
 * @param {Element} reference  the trigger element
 * @param {Element} floating   the panel element (already visible)
 * @param {{placement?: string, offset?: number, matchWidth?: boolean}} config
 * @returns {() => void} cleanup function that stops the auto-updater
 */
export const floatingAnchor = (reference, floating, config = {}) => {
    if (! reference || ! floating) {
        return () => {}
    }

    const placement = config.placement || 'bottom-end'
    const gap = config.offset ?? 6
    const matchWidth = config.matchWidth ?? false

    const middleware = [offset(gap), flip(), shift({ padding: 8 })]

    if (matchWidth) {
        middleware.push(size({
            apply({ rects, elements }) {
                elements.floating.style.minWidth = `${rects.reference.width}px`
            },
        }))
    }

    // Float above any stacking context now that we live on <body>.
    Object.assign(floating.style, { position: 'absolute', top: '0', left: '0' })

    return autoUpdate(reference, floating, () => {
        computePosition(reference, floating, { placement, middleware }).then(({ x, y }) => {
            Object.assign(floating.style, { left: `${x}px`, top: `${y}px` })
        })
    })
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

document.addEventListener('alpine:init', () => {
    // $float(reference, panel, config) → cleanup. For components that own their
    // open-state and want Floating UI positioning on a teleported panel.
    window.Alpine.magic('float', () => floatingAnchor)

    window.Alpine.data('wireDropdown', wireDropdown)
})

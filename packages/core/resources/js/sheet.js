/**
 * `x-sheet-dismiss="<closeExpression>"` — the gesture every bottom sheet shares,
 * modelled on iOS sheets (UISheetPresentationController).
 *
 * Placed on the sheet's grabber, which is the first child of the panel; the
 * panel is what moves. Only below the sheet breakpoint (`data-sheet-bp`); on a
 * desktop the panel is a floating dropdown or a dialog and nothing here runs.
 *
 * - **Detents.** `data-sheet-detents="medium,large"` (the default for lists)
 *   opens a tall sheet at about half the screen and lets it be pulled up to
 *   92 % of it; `"large"` (a dialog) opens at its full height straight away.
 *   The height is the panel's `--wire-sheet-h`, which the panel's own
 *   `max-h-[var(--wire-sheet-h,…)]` reads — a sheet shorter than the detent is
 *   simply as tall as its content.
 * - **Drag anywhere at the top.** The grab zone is the grabber and the panel's
 *   first 56px, not just a 40px pill.
 * - **Down to close, with velocity.** Pulled down past 90px — or flicked — it
 *   closes; from the large detent a slower pull settles on the medium one
 *   first. Short of that it springs back.
 * - **Scroll hand-off.** A drag down on a list that is already scrolled to its
 *   top moves the sheet instead, as on iOS.
 * - **Rubber band** past the largest detent, and the backdrop
 *   (`[data-sheet-backdrop]` beside the panel) fades as the sheet goes down.
 * - **A search keeps the sheet still.** A sheet holding a search field
 *   (`[data-sheet-search]`) opens at the large height and keeps it — fixed,
 *   not "up to" — so the list does not jump as the results narrow, and there
 *   is room for them above the keyboard the search brings up. The field stays
 *   pinned under the grabber while the list scrolls.
 * - **Keyboard.** iOS Safari lays the keyboard over the page; while a sheet is
 *   open it is lifted by the part of the layout viewport the keyboard covers
 *   (`visualViewport`), and its height is worked out again from what is left
 *   visible — lifted at the height it opened with, a sheet taller than the
 *   space above the keyboard would push its own search field off the top.
 *   iOS also scrolls the visual viewport when it brings the keyboard up, so
 *   both `resize` and `scroll` move it.
 * - **Reduce motion.** With the setting on, a release snaps to its detent
 *   instead of springing there.
 */
const MEDIUM = 0.56
const LARGE = 0.92
const GRAB_ZONE = 56

const viewportHeight = () => window.visualViewport?.height ?? window.innerHeight

export const registerSheetGesture = (Alpine) => {
    Alpine.directive('sheet-dismiss', (el, { expression }, { evaluateLater, cleanup }) => {
        const runClose = evaluateLater(expression)
        const panel = el.parentElement
        if (! panel) return

        const declared = (el.dataset.sheetDetents || 'medium,large').split(',').map((d) => d.trim())
        const hasSearch = () => !! panel.querySelector('[data-sheet-search]')
        // A searchable list has the large height only; see the header comment.
        let detents = declared
        const isMobile = () => window.matchMedia(`(max-width: ${parseFloat(el.dataset.sheetBp) || 639.98}px)`).matches
        const backdrop = () => panel.parentElement?.querySelector(':scope > [data-sheet-backdrop]')
            ?? panel.closest('[data-sheet-root]')?.querySelector('[data-sheet-backdrop]')

        let detent = detents[0]
        const heightFor = (d) => Math.round(viewportHeight() * (d === 'large' ? LARGE : MEDIUM))
        const applyDetent = (d) => {
            detent = d
            panel.style.setProperty('--wire-sheet-h', `${heightFor(d)}px`)
        }
        const contentHeight = () => panel.scrollHeight
        const canGrow = () => detents.includes('large') && detent !== 'large' && contentHeight() > heightFor(detent) + 4

        // Every open starts at the first detent. A sheet is shown by x-show,
        // which flips `display`; watch for that rather than for a click.
        const reset = () => {
            if (! isMobile()) return
            panel.style.transform = ''
            panel.style.transition = ''
            detents = hasSearch() ? ['large'] : declared
            applyDetent(detents[0])
            // Fixed rather than capped: a narrowing search must not shrink it.
            panel.style.height = hasSearch() ? 'var(--wire-sheet-h)' : ''
        }
        const shown = new MutationObserver(() => {
            if (getComputedStyle(panel).display !== 'none' && ! panel._wireSheetShown) {
                panel._wireSheetShown = true
                reset()
                liftAboveKeyboard()
            } else if (getComputedStyle(panel).display === 'none') {
                panel._wireSheetShown = false
                panel.style.bottom = ''
                panel.style.height = ''
            }
        })
        shown.observe(panel, { attributes: true, attributeFilter: ['style', 'class'] })
        reset()

        // ── Keyboard ───────────────────────────────────────────────────
        const liftAboveKeyboard = () => {
            if (! isMobile() || ! window.visualViewport || getComputedStyle(panel).position !== 'fixed') return
            const covered = window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop
            panel.style.bottom = covered > 1 ? `${Math.round(covered)}px` : ''
            // heightFor() reads the visual viewport, so this is the detent
            // measured against what the keyboard leaves visible.
            if (panel._wireSheetShown) applyDetent(detent)
        }
        window.visualViewport?.addEventListener('resize', liftAboveKeyboard)
        window.visualViewport?.addEventListener('scroll', liftAboveKeyboard)

        // ── The drag ───────────────────────────────────────────────────
        let start = null

        const scrollParentAtTop = (target) => {
            for (let node = target; node && node !== panel.parentElement; node = node.parentElement) {
                if (node.scrollHeight > node.clientHeight + 1 && getComputedStyle(node).overflowY !== 'visible') {
                    return node.scrollTop <= 0
                }
            }
            return true
        }

        const onStart = (e) => {
            if (! isMobile() || e.touches.length !== 1) return
            const touch = e.touches[0]
            const top = panel.getBoundingClientRect().top
            start = {
                y: touch.clientY,
                at: e.timeStamp,
                height: panel.getBoundingClientRect().height,
                // In the grab zone the sheet moves at once; elsewhere only a
                // downward drag on a list already at its top hands over.
                zone: touch.clientY - top <= GRAB_ZONE || el.contains(e.target),
                atTop: scrollParentAtTop(e.target),
                dragging: false,
                dy: 0,
            }
        }

        const onMove = (e) => {
            if (! start) return
            const dy = e.touches[0].clientY - start.y

            if (! start.dragging) {
                if (Math.abs(dy) < 6) return
                if (! start.zone && ! (dy > 0 && start.atTop)) {
                    start = null
                    return
                }
                start.dragging = true
                panel.style.transition = 'none'
            }

            e.preventDefault()
            start.dy = dy

            if (dy >= 0) {
                panel.style.transform = `translateY(${dy}px)`
                const fade = backdrop()
                if (fade) fade.style.opacity = String(Math.max(0, 1 - dy / Math.max(1, start.height)))
                return
            }

            // Upward: grow to the next detent, then a rubber band.
            panel.style.transform = ''
            const max = canGrow() || detent === 'large' ? heightFor('large') : start.height
            const wanted = start.height - dy
            const height = wanted <= max ? wanted : max + (wanted - max) * 0.15
            panel.style.setProperty('--wire-sheet-h', `${Math.round(height)}px`)
        }

        const onEnd = (e) => {
            if (! start) return
            const { dragging, dy, at } = start
            start = null
            if (! dragging) return

            const speed = dy / Math.max(1, (e?.timeStamp ?? at) - at)
            panel.style.transition = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'none'
                : 'transform 260ms cubic-bezier(.2,.9,.3,1), max-height 260ms cubic-bezier(.2,.9,.3,1)'
            panel.style.transform = ''
            const fade = backdrop()
            if (fade) fade.style.opacity = ''

            if (dy > 0) {
                const flick = dy > 40 && speed > 0.5
                if (detent === 'large' && detents.includes('medium') && ! flick && contentHeight() > heightFor('medium') && dy < 220) {
                    applyDetent('medium')
                } else if (dy > 90 || flick) {
                    runClose(() => {})
                } else {
                    applyDetent(detent)
                }
                return
            }

            const grow = detents.includes('large') && (-dy > 60 || speed < -0.5)
            applyDetent(grow ? 'large' : detent)
        }

        panel.addEventListener('touchstart', onStart, { passive: true })
        panel.addEventListener('touchmove', onMove, { passive: false })
        panel.addEventListener('touchend', onEnd)
        panel.addEventListener('touchcancel', onEnd)
        cleanup(() => {
            shown.disconnect()
            window.visualViewport?.removeEventListener('resize', liftAboveKeyboard)
            window.visualViewport?.removeEventListener('scroll', liftAboveKeyboard)
            panel.removeEventListener('touchstart', onStart)
            panel.removeEventListener('touchmove', onMove)
            panel.removeEventListener('touchend', onEnd)
            panel.removeEventListener('touchcancel', onEnd)
        })
    })
}

/**
 * Page scroll lock — one owner for every surface that covers the page.
 *
 * A modal, a bottom sheet and the admin drawer all need the page behind them to
 * stay put. Each used to do it its own way, or not at all: `overflow-hidden` on
 * `<body>` (which iOS Safari ignores — the page keeps scrolling under a finger),
 * or nothing, so a long form in a dialog scrolled the table behind it instead.
 *
 * Locks are counted per owner element, so two surfaces open at once (a nested
 * modal, a sheet over a modal) release the page only when the last one goes.
 * The lock sits on `<html>`, which iOS 16+ honours, and the scrollbar's width is
 * put back as padding so a desktop page does not jump sideways when it vanishes.
 */
const owners = new Set()
let saved = null

const apply = () => {
    const root = document.documentElement

    if (owners.size > 0 && saved === null) {
        const gutter = window.innerWidth - root.clientWidth
        saved = {
            overflow: root.style.overflow,
            paddingRight: root.style.paddingRight,
            overscroll: root.style.overscrollBehavior,
        }
        root.style.overflow = 'hidden'
        root.style.overscrollBehavior = 'none'
        if (gutter > 0) root.style.paddingRight = `${gutter}px`
        root.dataset.wireScrollLocked = ''
    } else if (owners.size === 0 && saved !== null) {
        root.style.overflow = saved.overflow
        root.style.paddingRight = saved.paddingRight
        root.style.overscrollBehavior = saved.overscroll
        delete root.dataset.wireScrollLocked
        saved = null
    }
}

export const lockScroll = (owner) => {
    owners.add(owner)
    apply()
}

export const unlockScroll = (owner) => {
    owners.delete(owner)
    apply()
}

/**
 * `x-scroll-lock="<openExpression>"` — lock the page while the expression is
 * truthy. Released when it turns falsy *and* when the element leaves the
 * document: an action modal is usually removed by the server's re-render, not
 * closed, and a lock that waited for `show = false` would outlive it.
 */
export const registerScrollLock = (Alpine) => {
    Alpine.directive('scroll-lock', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const isOpen = evaluateLater(expression || 'true')

        effect(() => isOpen((open) => (open ? lockScroll(el) : unlockScroll(el))))
        cleanup(() => unlockScroll(el))
    })

    window.wireScrollLock = { lock: lockScroll, unlock: unlockScroll }
}

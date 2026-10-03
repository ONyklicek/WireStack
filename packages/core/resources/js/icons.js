/*
 * The browser half of the icon sprite (IconSprite in wire-core).
 *
 * The server draws a repeated icon as `<svg …><use href="#wi-…"/></svg>` and
 * puts each `<symbol>` once per piece of markup it sends — inside the first such
 * `<svg>` of a component render, an island render or a partial. That is enough
 * for the piece to paint on its own, before this file has run.
 *
 * What it is not enough for is the piece's *neighbours*: an island re-rendered
 * around the icon that happened to carry a symbol takes the symbol with it,
 * while references to it elsewhere in the component stay. So every symbol seen
 * is copied, once, into a single sprite at the end of <body>, and `<use>` falls
 * back to it by id when the carrier goes. Duplicate ids are the point, not an
 * accident: the first in document order answers, and when it leaves, the next.
 *
 * Deliberately not an Alpine component and not registered on `alpine:init`: a
 * MutationObserver installed when the script runs sees every node that arrives
 * later — a Livewire morph, an island, a partial, a `wire:navigate` body swap,
 * an Alpine `x-if` stamping its template — without a hook per mechanism.
 */

const SPRITE_ID = 'wire-icon-sprite'
const SVG_NS = 'http://www.w3.org/2000/svg'
const SELECTOR = 'symbol[id^="wi-"]'

let sprite = null
let known = new Set()

/**
 * The page's sprite, made on first need. A `wire:navigate` visit replaces
 * <body> and takes the sprite with it, so a disconnected one starts over.
 */
function currentSprite() {
    if (sprite && sprite.isConnected) return sprite

    sprite = document.createElementNS(SVG_NS, 'svg')
    sprite.id = SPRITE_ID
    sprite.setAttribute('aria-hidden', 'true')
    sprite.setAttribute('focusable', 'false')
    // Out of flow and zero-sized rather than display:none, which some engines
    // have treated as "render nothing referenced from here" for paint servers.
    sprite.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden'
    known = new Set()

    document.body.appendChild(sprite)

    return sprite
}

function adopt(symbol) {
    const target = currentSprite()

    if (symbol.parentNode === target || known.has(symbol.id)) return

    known.add(symbol.id)
    target.appendChild(symbol.cloneNode(true))
}

function collect(node) {
    if (node.nodeType !== 1 || ! document.body) return

    if (node.matches(SELECTOR)) adopt(node)

    node.querySelectorAll(SELECTOR).forEach(adopt)
}

function start() {
    if (window.__wireIconSprite) return
    window.__wireIconSprite = true

    const scan = () => document.body && collect(document.body)

    new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) collect(node)
        }
    }).observe(document.documentElement, { childList: true, subtree: true })

    if (document.body) scan()
    else document.addEventListener('DOMContentLoaded', scan, { once: true })
}

start()

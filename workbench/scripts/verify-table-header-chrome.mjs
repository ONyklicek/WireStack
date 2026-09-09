/*
 * The chrome around the rows, read back from the browser rather than the markup.
 *
 * Every check here failed to be checkable server-side:
 *
 *  - **casing** — `<thead>` carries `uppercase` and every header looks uppercase
 *    in the HTML, but a sortable one renders inside a `<button>`, and the UA
 *    stylesheet sets `text-transform: none` on form controls. Only a computed
 *    style says which of the two won.
 *  - **pinning** — a sticky header pins to the nearest scrolling ancestor, and
 *    the table's wrapper is already one (`overflow-x: auto` computes the other
 *    axis to `auto`). Whether the header actually stays put is a question about
 *    scroll positions, not classes.
 *  - **the scrollbar** — the sign that a clipped region has more in it. macOS
 *    and iOS draw an OVERLAY scrollbar that fades out after the last scroll, so
 *    a table nobody has touched shows nothing; declaring `::-webkit-scrollbar`
 *    is what opts an element back onto a classic, always-present one. Whether
 *    that worked is a question about laid-out pixels: a classic scrollbar takes
 *    a gutter out of the element's client box, an overlay one takes none.
 */

import { openPage, checker, until } from './lib/cdp.mjs';

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-sticky-header';

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  // `showScrollbars`, because the default `--hide-scrollbars` makes the whole
  // second half of this file untestable: with it Chrome reports a zero gutter on
  // every element, styled or not, so a check over the scrollbar rules would pass
  // on a stylesheet that had never been applied.
  await openPage({ url, shotPrefix: 'table-header-chrome', width: 900, height: 900, showScrollbars: true });
const { check, finish } = checker();

try {
  await waitFor('document.querySelectorAll("tbody tr").length > 10');

  // ── Casing ────────────────────────────────────────────────────────────────
  // Read the transform off whatever element actually holds the label: the
  // button on a sortable header, the <th> on the rest. A table whose headers
  // disagree is the bug, whichever way round it is.
  const transforms = await eval_(`
    JSON.stringify(Array.from(document.querySelectorAll('thead th'))
      .map((th) => {
        const btn = th.querySelector('button[data-testid^="table-sort-"]')
        return th.textContent.trim() ? getComputedStyle(btn ?? th).textTransform : null
      })
      .filter(Boolean))
  `);
  const cases = JSON.parse(transforms);

  check('every header label is cased the same way', new Set(cases).size === 1, cases.join(', '));
  check('and that way is the uppercase the thead asks for', cases.every((c) => c === 'uppercase'), cases[0]);

  await shot('01-headers');

  // ── Pinning ───────────────────────────────────────────────────────────────
  const scroller = 'document.querySelector(".wire-scroller")';

  const capped = await eval_(`(() => {
    const el = ${scroller}
    return JSON.stringify({ scrollable: el.scrollHeight > el.clientHeight + 1, height: el.clientHeight })
  })()`);
  check('the scroll region is capped, so it can scroll at all', JSON.parse(capped).scrollable, capped);

  // The gutter a scrollbar takes out of the element's client box. An overlay
  // scrollbar — the macOS default, and what an unstyled element still gets —
  // is painted over the content and takes none, which is the whole difference
  // this measures.
  const gutter = (axis) => eval_(`(() => {
    const el = ${scroller}
    return ${axis === 'y' ? 'el.offsetWidth - el.clientWidth' : 'el.offsetHeight - el.clientHeight'}
  })()`);

  // Before anything is scrolled or hovered: the capped region already has rows
  // below the fold, and the scrollbar is what says so.
  const vertical = await gutter('y');
  check('the vertical scrollbar is laid out before anything is scrolled', vertical > 0, `${vertical}px`);
  check('and it is the styled 10px one, not the platform default', vertical === 10, `${vertical}px`);

  // The comparison that makes the number mean something: an element with the
  // same overflow and no stylesheet gets the overlay scrollbar back.
  const unstyled = await eval_(`(() => {
    const d = document.createElement('div')
    d.style.cssText = 'width:200px;height:100px;overflow:auto;position:absolute;top:-9999px'
    d.innerHTML = '<div style="width:900px;height:900px"></div>'
    document.body.appendChild(d)
    const g = d.offsetWidth - d.clientWidth
    d.remove()
    return g
  })()`);
  check('an unstyled scroller beside it gets none — so the rules are doing it', unstyled === 0, `${unstyled}px`);

  const pinned = await eval_(`(() => {
    const el = ${scroller}
    const thead = document.querySelector('thead')
    const before = thead.getBoundingClientRect().top
    el.scrollTop = 300
    return JSON.stringify({
      before: Math.round(before),
      after: Math.round(thead.getBoundingClientRect().top),
      regionTop: Math.round(el.getBoundingClientRect().top),
      scrolled: el.scrollTop,
    })
  })()`);
  const p = JSON.parse(pinned);

  check('the rows scrolled', p.scrolled > 0, `scrollTop=${p.scrolled}`);
  check('the header stayed at the top of the region', Math.abs(p.after - p.regionTop) <= 1, pinned);

  // A translucent header would show the rows travelling under it.
  const opaque = await eval_(`getComputedStyle(document.querySelector('thead')).backgroundColor`);
  check('and it is opaque', ! /rgba\\([^)]*,\\s*0?\\.\\d+\\)/.test(opaque), opaque);

  await shot('02-pinned');

  // Nothing is laid over the rows any more. The three gradients that used to be
  // here dimmed the pinned header row, the rules between rows and the first
  // characters of the first column, and a stale one after a morph was a grey
  // band on a table that fitted.
  check('and nothing is overlaid on the rows', (await eval_(`document.querySelectorAll('[data-testid^=table-scroll-shadow]').length`)) === 0);

  await eval_(`(() => { ${scroller}.scrollTop = 0 })()`);

  // ── The horizontal axis ───────────────────────────────────────────────────
  // Widen the table past its region rather than shipping a fixture wide enough
  // to overflow at every width a driver might run at. A gutter that appears
  // from this alone — nothing scrolled, no `scroll` event — is the point: it is
  // the box reporting its own state, where the gradients needed a ResizeObserver
  // to notice the same thing.
  const before = await gutter('x');
  check('a table that fits shows no horizontal scrollbar', before === 0, `${before}px`);

  await eval_(`(() => { ${scroller}.querySelector('table').style.minWidth = '1600px' })()`);

  const overflows = await until(() => eval_(`(() => { const el = ${scroller}; return el.scrollWidth > el.clientWidth + 1 })()`));
  check('the table is now wider than the region', overflows === true, String(overflows));

  await until(async () => (await gutter('x')) > 0);
  const horizontal = await gutter('x');
  check('and the horizontal scrollbar is there without anything being scrolled', horizontal > 0, `${horizontal}px`);
  check('at the same styled height', horizontal === 10, `${horizontal}px`);

  // A scrollbar says how far, which is the half a gradient could not: the thumb
  // is a fraction of the track, and the fraction is what is on screen.
  const proportion = await eval_(`(() => { const el = ${scroller}; return Math.round(100 * el.clientWidth / el.scrollWidth) })()`);
  check('and it says how much of the table is on screen', proportion > 0 && proportion < 100, `${proportion}%`);

  await shot('03-scrolled-across');
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}

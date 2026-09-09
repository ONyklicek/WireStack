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
 *  - **the edge gradients** — they are drawn from `scrollLeft`, in Alpine.
 */

import { openPage, checker, until } from './lib/cdp.mjs';

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-sticky-header';

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'table-header-chrome', width: 900, height: 900 });
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
  const scroller = 'document.querySelector("[data-testid=table-scroll-shadow-end]").parentElement.querySelector(".overflow-x-auto")';

  const capped = await eval_(`(() => {
    const el = ${scroller}
    return JSON.stringify({ scrollable: el.scrollHeight > el.clientHeight + 1, height: el.clientHeight })
  })()`);
  check('the scroll region is capped, so it can scroll at all', JSON.parse(capped).scrollable, capped);

  // Whether an edge gradient is on screen. Used by both halves below.
  const shown = (id) => `(() => {
    const el = document.querySelector('[data-testid=table-scroll-shadow-${id}]')
    return el && getComputedStyle(el).display !== 'none'
  })()`;

  // Before anything is scrolled: the capped region already has rows below the
  // fold, and the sliced last row is exactly what the gradient exists to explain.
  check('the bottom edge is shadowed before the region is scrolled', await eval_(shown('bottom')));

  // Shown is not the same as drawn: a `bg-gradient-*` utility that missed the
  // consumer's Tailwind build leaves an element that is present, sized, and
  // displayed — and completely invisible.
  const bottomPaint = await eval_(`getComputedStyle(document.querySelector('[data-testid=table-scroll-shadow-bottom]')).backgroundImage`);
  check('and it actually paints a gradient', bottomPaint.startsWith('linear-gradient'), bottomPaint);

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

  check('and still shadowed part-way down', await eval_(shown('bottom')));

  await shot('02-pinned');

  // At the very bottom there is nothing more to promise, so the gradient goes.
  await eval_(`(() => { const el = ${scroller}; el.scrollTop = el.scrollHeight })()`);
  await until(async () => (await eval_(shown('bottom'))) === false);
  check('and gone once the last row is reached', (await eval_(shown('bottom'))) === false);

  // The top edge is deliberately unshadowed: the pinned header is already the
  // marker for what is above, and a fourth gradient would only dim it.
  check('no top gradient is rendered at all', (await eval_(`!! document.querySelector('[data-testid=table-scroll-shadow-top]')`)) === false);

  await eval_(`(() => { ${scroller}.scrollTop = 0 })()`);

  // ── Horizontal edges ──────────────────────────────────────────────────────
  // Widen the table past its region rather than shipping a fixture wide enough
  // to overflow at every width a driver might run at. That also puts the wiring
  // itself under test: nothing is scrolled here and no `scroll` event fires, so
  // the gradient can only appear if the ResizeObserver on the table noticed.
  await eval_(`(() => { ${scroller}.querySelector('table').style.minWidth = '1600px' })()`);

  const overflows = await until(() => eval_(`(() => { const el = ${scroller}; return el.scrollWidth > el.clientWidth + 1 })()`));
  check('the table is now wider than the region', overflows === true, String(overflows));

  await until(() => eval_(shown('end')));
  check('the end edge is shadowed while there is more table to the right', await eval_(shown('end')));

  // Shown is not the same as drawn. `bg-gradient-to-l` and its `from-` stop have
  // to survive the consumer's Tailwind build; when they do not, the element is
  // still there, still sized, still displayed — and completely invisible.
  const painted = await eval_(`getComputedStyle(document.querySelector('[data-testid=table-scroll-shadow-end]')).backgroundImage`);
  check('and it actually paints a gradient', painted.startsWith('linear-gradient'), painted);

  check('the start edge is not, at scrollLeft 0', (await eval_(shown('start'))) === false);

  await eval_(`(() => { const el = ${scroller}; el.scrollLeft = el.scrollWidth; el.dispatchEvent(new Event('scroll')) })()`);
  await until(() => eval_(shown('start')));

  check('scrolled to the far side, the start edge is shadowed', await eval_(shown('start')));
  check('and the end edge is not', (await eval_(shown('end'))) === false);

  await shot('03-scrolled-across');
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}

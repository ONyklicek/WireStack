/*
 * The pinned actions column, read back from a browser that is actually scrolling.
 *
 * Everything the server can say about `Table::stickyActions()` is a class string,
 * and every failure this feature has is a failure of composition that no class
 * string reveals:
 *
 *  - **the pin itself** — whether `position: sticky` resolves at all depends on
 *    the ancestors, not on the cell: one `overflow: hidden` anywhere above it and
 *    the column scrolls away with everything else, markup unchanged;
 *  - **opacity** — the pinned cell is transparent by default and is made opaque
 *    by three stacked layers (Support\StickyColumn). Whether the columns really
 *    disappear behind it is a question about painted pixels and hit-testing;
 *  - **the row's colour** — the pane is supposed to carry the row's stripe, hover
 *    and selection, and it gets them by inheriting a computed value twice. Only a
 *    computed style says whether the chain holds;
 *  - **layering** — a sticky cell creates a stacking context, and a row action's
 *    dropdown is teleported out of it. That is exactly the case `floorZ` in
 *    dropdown.js exists for, and it has never had a pinned column to fail on.
 */

import { openPage, checker, until, sleep } from './lib/cdp.mjs';

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-sticky-actions';

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'table-sticky-actions', width: 900, height: 900 });
const { check, finish } = checker();

// The scroll region, found the way the header-chrome driver finds it: through the
// edge gradient that is guaranteed to sit beside it.
const scroller = 'document.querySelector("[data-testid=table-scroll-shadow-end]").parentElement.querySelector(".overflow-x-auto")';

// The pinned cell of the second body row, and an ordinary cell in the same row.
const pinned = 'document.querySelectorAll("tbody tr")[1].querySelector("td.sticky")';
const ordinary = 'document.querySelectorAll("tbody tr")[1].querySelector("td:not(.sticky)")';

const rects = async () => JSON.parse(await eval_(`(() => {
  const el = ${scroller}
  return JSON.stringify({
    region: Math.round(el.getBoundingClientRect().right),
    pinned: Math.round(${pinned}.getBoundingClientRect().right),
    ordinary: Math.round(${ordinary}.getBoundingClientRect().left),
    scrollLeft: Math.round(el.scrollLeft),
  })
})()`));

try {
  await waitFor('document.querySelectorAll("tbody tr").length > 10');

  // ── There is something to pin against ─────────────────────────────────────
  const overflows = await eval_(`(() => { const el = ${scroller}; return el.scrollWidth > el.clientWidth + 1 })()`);
  check('the table is wider than the region holding it', overflows === true, String(overflows));

  const pinnedCount = await eval_(`document.querySelectorAll("td.sticky, th.sticky").length`);
  check('every surface of the actions column is pinned', pinnedCount > 40, `${pinnedCount} pinned cells`);

  const resolved = await eval_(`getComputedStyle(${pinned}).position`);
  check('and the pin actually resolves — no clipping ancestor ate it', resolved === 'sticky', resolved);

  await shot('01-loaded');

  // ── It stays while the rest travels ───────────────────────────────────────
  const before = await rects();
  check('the pinned cell starts at the region edge', Math.abs(before.pinned - before.region) <= 2, JSON.stringify(before));

  await eval_(`(() => { const el = ${scroller}; el.scrollLeft = el.scrollWidth; el.dispatchEvent(new Event('scroll')) })()`);
  await until(async () => (await rects()).scrollLeft > 0);

  const after = await rects();
  check('the row scrolled', after.scrollLeft > 100, `scrollLeft=${after.scrollLeft}`);
  check('an ordinary cell travelled with it', after.ordinary < before.ordinary - 100, `${before.ordinary} → ${after.ordinary}`);
  check('the pinned cell did not', Math.abs(after.pinned - after.region) <= 2, JSON.stringify(after));

  await shot('02-scrolled-across');

  // ── Nothing shows through it ──────────────────────────────────────────────
  // The hit-test is the honest question: a cell that paints under the pane would
  // still answer at the point the pane occupies.
  const hit = await eval_(`(() => {
    const cell = ${pinned}
    const r = cell.getBoundingClientRect()
    const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
    return el ? (cell.contains(el) ? 'inside' : el.tagName + '.' + el.className) : 'nothing'
  })()`);
  check('the point over the pinned cell belongs to the pinned cell', hit === 'inside', hit);

  const button = await eval_(`(() => {
    const el = document.querySelectorAll('tbody tr')[1].querySelector('[data-testid="action-open"]')
    const r = el.getBoundingClientRect()
    const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
    return hit && el.contains(hit) ? 'button' : (hit ? hit.tagName + '.' + hit.className : 'nothing')
  })()`);
  check('and its action button is the thing a click would land on', button === 'button', button);

  // The layers themselves: an opaque surface, then the row's own colour back on
  // top of it. If the surface is translucent the whole design is decorative.
  const surface = await eval_(`getComputedStyle(${pinned}.querySelector('div')).backgroundColor`);
  check('the surface layer is opaque', /^rgb\(/.test(surface), surface);

  const covers = await eval_(`(() => {
    const cell = ${pinned}
    const layer = cell.querySelector('div')
    const c = cell.getBoundingClientRect(), l = layer.getBoundingClientRect()
    return Math.round(c.width - l.width) <= 2 && Math.round(c.height - l.height) <= 2
  })()`);
  check('and it covers the cell it is under', covers === true);

  // ── The pane carries the row's own colour ─────────────────────────────────
  // Striped table: two adjacent rows have different backgrounds, and the pinned
  // cell's top layer has to report each row's own — that is the inheritance
  // chain row → cell → layer doing its work.
  const stripes = JSON.parse(await eval_(`(() => {
    const read = (i) => {
      const row = document.querySelectorAll('tbody tr')[i]
      const layers = row.querySelector('td.sticky').querySelectorAll('div')
      return {
        row: getComputedStyle(row).backgroundColor,
        top: getComputedStyle(layers[1]).backgroundColor,
      }
    }
    return JSON.stringify({ even: read(0), odd: read(1) })
  })()`));

  check('the pinned cell reports its own row\'s colour', stripes.even.top === stripes.even.row, JSON.stringify(stripes.even));
  check('and the striped row reports the stripe, not its neighbour', stripes.odd.top === stripes.odd.row, JSON.stringify(stripes.odd));
  check('which are two different colours, or this proves nothing', stripes.even.row !== stripes.odd.row, JSON.stringify(stripes));

  // Selection arrives from Alpine at runtime, long after the class strings were
  // written: the same chain has to pick it up with no server round trip.
  const beforeSelect = await eval_(`getComputedStyle(document.querySelectorAll('tbody tr')[1].querySelector('td.sticky').querySelectorAll('div')[1]).backgroundColor`);
  await eval_(`document.querySelectorAll('tbody tr')[1].querySelector('[data-select-cell]').click()`);
  await sleep(400);
  const afterSelect = await eval_(`getComputedStyle(document.querySelectorAll('tbody tr')[1].querySelector('td.sticky').querySelectorAll('div')[1]).backgroundColor`);

  check('a selected row repaints its pinned cell too', beforeSelect !== afterSelect, `${beforeSelect} → ${afterSelect}`);
  await shot('03-selected');

  // ── Both axes at once ─────────────────────────────────────────────────────
  // The two z tiers are decided here: the pinned header cell is pinned on both
  // axes, and the pinned body cells travel up underneath it.
  await eval_(`(() => { const el = ${scroller}; el.scrollTop = 300; el.dispatchEvent(new Event('scroll')) })()`);
  await sleep(300);

  const corner = await eval_(`(() => {
    const th = document.querySelector('thead th.sticky')
    const r = th.getBoundingClientRect()
    const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
    return el ? (th.contains(el) ? 'header' : el.tagName + '.' + el.className) : 'nothing'
  })()`);
  check('the pinned header stays above the pinned rows travelling under it', corner === 'header', corner);

  await shot('04-both-axes');

  // ── The dropdown teleported out of a stacking context ─────────────────────
  await eval_(`(() => { const el = ${scroller}; el.scrollTop = 0; el.dispatchEvent(new Event('scroll')) })()`);
  await sleep(200);
  await eval_(`document.querySelectorAll('tbody tr')[1].querySelector('[data-testid="action-group-trigger"]').click()`);

  // Every row carries its own panel, and a closed one is in the document with a
  // zero rect — so both the wait and the checks below have to find the VISIBLE
  // item rather than the first one that matches. A presence probe here passes
  // against a menu that never opened, which is exactly what it did.
  const openItem = `(() => Array.from(document.querySelectorAll('[data-testid="menu-action-duplicate"]'))
    .find((el) => el.getBoundingClientRect().width > 0))()`;

  await until(() => eval_(`!! ${openItem}`));

  const menu = await eval_(`(() => {
    const item = ${openItem}
    if (! item) return JSON.stringify({ opened: false })
    const r = item.getBoundingClientRect()
    const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
    return JSON.stringify({
      opened: true,
      teleported: ! item.closest('td'),
      onTop: !! hit && item.contains(hit),
      inFront: !! hit && hit.closest('td.sticky') === null,
    })
  })()`);
  const m = JSON.parse(menu);

  check('the menu inside the pinned cell opens', m.opened === true, menu);
  check('and opens out of the cell, past the stacking context pinning created', m.teleported === true, menu);
  check('nothing in the pane is painted over it', m.onTop === true && m.inFront === true, menu);

  await shot('05-menu-open');
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}

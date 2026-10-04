/*
 * A page-pinned table header inside the real admin shell.
 *
 * The preview driver (verify-table-sticky-page) proves the mechanism on a bare
 * page. This one runs where an application actually uses it: a resource list in
 * the admin layout, with the sticky top bar over the window and the sidebar
 * beside the content. What only this page can say:
 *
 *  - the header stops at the bottom of the top bar (`--wire-sticky-top`), not
 *    at the top of the window underneath it;
 *  - the header is what is visible there — the bar's `z-30` is over it, so a
 *    header one pixel too high is a header nobody can read;
 *  - it comes back to that line after a re-render (sort, search), and stops at
 *    the bottom of a table the search made shorter.
 */

import { openPage, checker, until } from './lib/cdp.mjs';

const BASE = process.env.PREVIEW_URL ?? process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085';
const url = `${BASE}/previews/routed/gesture-rows`;

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'admin-sticky-header', width: 1280, height: 700 });
const { check, finish } = checker();

const HEAD = `document.querySelector('thead[data-wire-sticky-head]')`;

const measure = () => eval_(`(() => {
  const head = ${HEAD}
  const bar = document.querySelector('[data-wire="admin-topbar"]')
  const table = head.closest('table')
  const h = head.getBoundingClientRect()
  const t = table.getBoundingClientRect()
  const x = h.left + Math.min(40, h.width / 2)
  const y = h.top + h.height / 2
  return JSON.stringify({
    head: Math.round(h.top),
    headBottom: Math.round(h.bottom),
    bar: Math.round(bar.getBoundingClientRect().bottom),
    tableTop: Math.round(t.top),
    tableBottom: Math.round(t.bottom),
    visible: head.contains(document.elementFromPoint(x, y)),
    pinned: head.hasAttribute('data-pinned'),
  })
})()`).then(JSON.parse);

const atBar = async () => {
  const m = await measure();
  return Math.abs(m.head - m.bar) <= 1 ? m : false;
};

try {
  await waitFor(`document.querySelectorAll('tbody tr').length >= 40 && !! document.querySelector('[data-wire="admin-topbar"]')`);

  const rest = await measure();
  check('the list renders inside the admin shell, under its top bar', rest.tableTop >= rest.bar, JSON.stringify(rest));
  check('and at rest the header is where the table puts it', ! rest.pinned && rest.head === rest.tableTop, JSON.stringify(rest));
  check('the rows are not in a capped box', (await eval_(`document.querySelector('.wire-scroller').style.maxHeight`)) === '');

  // ── Scroll the window ─────────────────────────────────────────────────────
  await eval_(`window.scrollTo(0, ${HEAD}.closest('table').getBoundingClientRect().top + window.scrollY + 400)`);
  const pinned = await until(atBar);
  check('scrolled, the header stops at the bottom of the top bar', !! pinned, JSON.stringify(pinned || await measure()));
  check('and is the thing visible there, not the bar over it', !! pinned && pinned.visible);
  check('and is marked pinned', !! pinned && pinned.pinned);

  // ── Driven by the scroll, not by a listener ───────────────────────────────
  // The shudder this exists to prevent: a header positioned from a `scroll`
  // listener lands a frame after the compositor has already moved the rows.
  // Timing cannot be read from here — the main thread samples a scroll-driven
  // animation at the next frame too, while the compositor draws it in step —
  // so what is checked is the mechanism: a scroll timeline animation on the
  // header, its range the one the page needs, and no inline transform at all.
  const driven = JSON.parse(await eval_(`(() => {
    const head = ${HEAD}
    const animations = head.getAnimations()
    const start = parseFloat(head.style.getPropertyValue('--wire-sticky-start'))
    const bar = document.querySelector('[data-wire="admin-topbar"]').getBoundingClientRect().bottom
    const tableTop = head.closest('table').getBoundingClientRect().top + window.scrollY
    return JSON.stringify({
      timeline: head.hasAttribute('data-wire-sticky-timeline'),
      animations: animations.length,
      scrollDriven: animations[0]?.timeline instanceof ScrollTimeline,
      inline: head.style.transform,
      start,
      expected: Math.round(tableTop - bar),
    })
  })()`));
  check('the scroll timeline moves the header, not a scroll listener',
    driven.timeline && driven.animations === 1 && driven.scrollDriven && driven.inline === '', JSON.stringify(driven));
  check('and its range starts where the table meets the bar', Math.abs(driven.start - driven.expected) <= 1, JSON.stringify(driven));

  await shot('01-pinned-under-bar');

  // ── A re-render while pinned ──────────────────────────────────────────────
  const firstRow = () => eval_(`document.querySelector('tbody tr')?.textContent.trim()`);
  const before = await firstRow();
  await eval_(`document.querySelector('button[data-testid="table-sort-name"]').click()`);
  check('the sort re-rendered the rows', (await until(async () => (await firstRow()) !== before)) === true);
  const resorted = await until(atBar);
  check('and the header is back under the bar after the morph', !! resorted, JSON.stringify(resorted || await measure()));

  await shot('02-after-sort');

  // ── Past the end of the table ─────────────────────────────────────────────
  await eval_(`window.scrollTo(0, document.documentElement.scrollHeight)`);
  const bounded = await until(async () => {
    const m = await measure();
    return m.headBottom <= m.tableBottom + 1 ? m : false;
  });
  check('scrolled past the table, the header leaves with the last row', !! bounded, JSON.stringify(bounded || await measure()));

  // ── A search that shortens the table ──────────────────────────────────────
  await eval_(`window.scrollTo(0, 0)`);
  // The table's own search, not the top bar's global one.
  await eval_(`(() => {
    const input = Array.from(document.querySelectorAll('input')).find((el) => el.getAttribute('wire:model.live.debounce.300ms') === 'tableState.search')
    input.value = 'Record 1'
    input.dispatchEvent(new Event('input', { bubbles: true }))
  })()`);
  const filtered = await until(async () => {
    const n = await eval_(`document.querySelectorAll('tbody tr').length`);
    return n > 0 && n < 40;
  });
  check('the search narrowed the rows', !! filtered);

  const unpinned = await until(async () => {
    const m = await measure();
    return ! m.pinned && m.head === m.tableTop ? m : false;
  });
  check('and at the top of the page nothing is moved', !! unpinned, JSON.stringify(unpinned || await measure()));

  // The shorter table changed where the header has to stop; the range must
  // have followed it, or the header would ride on past the last row.
  await eval_(`window.scrollTo(0, document.documentElement.scrollHeight)`);
  const shortBounded = await until(async () => {
    const m = await measure();
    return m.headBottom <= m.tableBottom + 1 && m.head >= m.tableTop ? m : false;
  });
  check('and in the shorter table the header still stops at its last row', !! shortBounded, JSON.stringify(shortBounded || await measure()));

  await shot('03-searched');
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}

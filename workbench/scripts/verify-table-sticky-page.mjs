/*
 * A header pinned to the page, read back from scroll positions.
 *
 * `stickyHeader()` with no height cannot use CSS `sticky`: the table's wrapper
 * carries `overflow-x: auto`, which makes it the scrollport a sticky `<thead>`
 * pins to, and it never scrolls vertically. `wire-table-sticky.js` translates
 * the header instead. Whether it lands at the top of the window, stops at the
 * table's bottom, and survives a morph rewriting its `style` are questions about
 * the browser, not the markup.
 */

import { openPage, checker, until } from './lib/cdp.mjs';

const url = process.env.PREVIEW_URL ?? 'http://127.0.0.1:8085/previews/table-sticky-header-page';

const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } =
  await openPage({ url, shotPrefix: 'table-sticky-page', width: 900, height: 600 });
const { check, finish } = checker();

const headTop = () => eval_(`Math.round(document.querySelector('thead[data-wire-sticky-head]').getBoundingClientRect().top)`);

try {
  await waitFor('document.querySelectorAll("tbody tr").length > 10');

  const region = JSON.parse(await eval_(`(() => {
    const el = document.querySelector('.wire-scroller')
    return JSON.stringify({ capped: el.style.maxHeight !== '', scrolls: el.scrollHeight > el.clientHeight + 1 })
  })()`));
  check('the scroll region is not capped', ! region.capped && ! region.scrolls, JSON.stringify(region));

  // Scroll the page well into the table.
  await eval_(`(() => {
    const table = document.querySelector('thead[data-wire-sticky-head]').closest('table')
    window.scrollTo(0, table.getBoundingClientRect().top + window.scrollY + 300)
  })()`);

  const pinned = await until(async () => Math.abs(await headTop()) <= 1);
  check('the header sits at the top of the window', pinned === true, `top=${await headTop()}`);
  check('and it is marked pinned', await eval_(`document.querySelector('thead[data-wire-sticky-head]').hasAttribute('data-pinned')`));

  await shot('01-pinned');

  // A re-render rewrites the thead's style to what the server sent; the
  // header has to come back to the pin line on its own.
  const firstRow = () => eval_(`document.querySelector('tbody tr')?.textContent.trim()`);
  const before = await firstRow();
  await eval_(`document.querySelector('button[data-testid^="table-sort-"]')?.click()`);
  const morphed = await until(async () => (await firstRow()) !== before);
  check('the sort re-rendered the rows', morphed === true);
  const afterMorph = await until(async () => Math.abs(await headTop()) <= 1);
  check('and stays there through a re-render', afterMorph === true, `top=${await headTop()}`);

  // Past the end of the table, the header leaves with the last row.
  await eval_(`window.scrollTo(0, document.documentElement.scrollHeight)`);
  const bounded = await until(() => eval_(`(() => {
    const head = document.querySelector('thead[data-wire-sticky-head]')
    const table = head.closest('table')
    return head.getBoundingClientRect().bottom <= table.getBoundingClientRect().bottom + 1
  })()`));
  check('and never runs past the bottom of its table', bounded === true);

  await eval_(`window.scrollTo(0, 0)`);
  const rested = await until(async () => (await eval_(`document.querySelector('thead[data-wire-sticky-head]').style.transform`)) === '');
  check('back at the top, the header is not moved at all', rested === true);

  await shot('02-rest');
} finally {
  finish({ consoleErrors, badResponses, shotDir });
  await close();
}

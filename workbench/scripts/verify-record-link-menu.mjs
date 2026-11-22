import { openPage, checker } from './lib/cdp.mjs';

/*
 * A right click on the text of a recordUrl() cell opens the row's menu.
 *
 * recordUrl() wraps every plain cell in a link to the record, and the row
 * controller keeps any link inside a row to itself — so a right click on the
 * text was handed to the browser's link menu and the row's own menu answered
 * only in the cell padding. A wide, two-line name column was nearly all link.
 * The record link now carries `data-record-link` and the context menu treats
 * it as the row, while a plain click still follows it. Pest sees the
 * attribute; only a browser can say which menu opens.
 *
 * Fixture: /previews/table-record-link — recordUrl() plus onContextMenu()
 * record actions on one table.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/table-record-link`;
const { check, finish } = checker();

const page_ = await openPage({ url, shotPrefix: 'record-link-menu', width: 1300, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! window.Alpine && document.querySelectorAll('tbody tr[data-row-key] [data-record-link]').length > 2`);

  await eval_(`(() => {
    window.link = (i) => document.querySelectorAll('tbody tr[data-row-key] [data-testid="table-cell-name"] [data-record-link]')[i];
    // Dispatched on the link itself — the text a person right-clicks — and
    // reported back whether the page claimed it (preventDefault) or let the
    // browser's own menu through.
    window.rightClick = (el) => {
      const r = el.getBoundingClientRect();
      return el.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, button: 2, clientX: r.left + 5, clientY: r.top + 5 }));
    };
    // Fixed and teleported, so offsetParent is always null: ask the style.
    window.openMenus = () => [...document.querySelectorAll('[data-record-menu]')].filter(p => getComputedStyle(p).display !== 'none');
    return true;
  })()`);

  check('the name cell is wrapped in the record link', await eval_(`!! link(1) && link(1).getAttribute('href').includes('user=')`));

  // ── 1. A right click on the link text opens the row menu ─────────────────
  const browserMenu = await eval_(`rightClick(link(1))`);
  await waitFor(`openMenus().length === 1`, { timeout: 2000 }).catch(() => {});
  check('the browser\'s link menu is not let through', browserMenu === false);
  check('the row\'s context menu opens', (await eval_(`openMenus().length`)) === 1);
  check('it is the menu of the row that was clicked', await eval_(`openMenus()[0]?.dataset.recordMenu === link(1).closest('tr[data-row-key]').dataset.rowKey`));
  check('the menu offers the row actions', await eval_(`/Edit/.test(openMenus()[0]?.textContent ?? '')`));
  await shot('01-row-menu-on-link');

  // ── 2. A plain click still follows the link ──────────────────────────────
  await eval_(`document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))`);
  const href = await eval_(`link(2).getAttribute('href')`);
  await eval_(`link(2).click()`);
  await waitFor(`location.href.includes(${JSON.stringify(href)})`, { timeout: 5000 }).catch(() => {});
  check('a click on the link still opens the record', await eval_(`location.href.includes(${JSON.stringify(href)})`), await eval_(`location.href`));
} catch (error) {
  check('driver ran without error', false, error.message);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });

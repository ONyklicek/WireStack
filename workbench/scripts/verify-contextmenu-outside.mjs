import { openPage, checker } from './lib/cdp.mjs';

/*
 * A right click outside an open floating panel closes it.
 *
 * Every floating panel closes on `@click.outside`, and a right click fires no
 * `click` — only `contextmenu`. So a SelectColumn's list stayed open while the
 * right click opened the row's context menu next to it, and two panels hung
 * over the table at once. Each panel now carries `@contextmenu.outside` with
 * the same guard as its click twin. Pest sees the attribute; only a browser
 * can say that Alpine closes the panel on it.
 *
 * Fixture: /previews/spa-navigate-table — a SelectColumn and an onContextMenu()
 * record action on one table.
 */

const url = process.env.PREVIEW_URL ?? `${process.env.PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/spa-navigate-table`;
const { check, finish } = checker();

const page_ = await openPage({ url, shotPrefix: 'contextmenu-outside', width: 1300, height: 900 });
const { eval_, waitFor, shot, shotDir, consoleErrors, badResponses, close } = page_;

try {
  await waitFor(`!! window.Alpine && document.querySelectorAll('[data-testid="table-editable-status"] [data-testid="select-trigger"]').length > 2`);

  await eval_(`(() => {
    window.trigger = (i) => document.querySelectorAll('[data-testid="table-editable-status"] [data-testid="select-trigger"]')[i];
    window.isOpen = (i) => Alpine.$data(trigger(i)).open === true;
    window.rowCell = (i) => document.querySelectorAll('tbody tr[data-row-key] [data-testid="table-cell-name"]')[i];
    window.rightClick = (el) => {
      const r = el.getBoundingClientRect();
      el.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, button: 2, clientX: r.left + 10, clientY: r.top + 5 }));
    };
    // Fixed and teleported, so offsetParent is always null: ask the style.
    window.recordMenuOpen = () => [...document.querySelectorAll('[data-record-menu]')].some(p => getComputedStyle(p).display !== 'none');
    // What the user sees, not the flag: any option list still drawn.
    window.listsDrawn = () => [...document.querySelectorAll('[role="listbox"]')].filter(l => { const r = l.getBoundingClientRect(); return r.height > 0 && getComputedStyle(l).visibility !== 'hidden' && l.closest('[style*="display: none"]') === null; }).length;
    return true;
  })()`);

  // ── 1. The select opens ──────────────────────────────────────────────────
  check('no option list is drawn before anything opens', (await eval_(`listsDrawn()`)) === 0, `${await eval_(`listsDrawn()`)} drawn`);
  await eval_(`trigger(0).click()`);
  await waitFor(`isOpen(0)`);
  check('opening it draws its list', (await eval_(`listsDrawn()`)) === 1, `${await eval_(`listsDrawn()`)} drawn`);
  check('the status select opens', await eval_(`isOpen(0)`));

  // ── 2. A right click inside its own panel leaves it open ─────────────────
  await eval_(`(() => {
    const panel = Alpine.$data(trigger(0)).$refs?.panel ?? document.querySelector('[x-ref="panel"]:not([style*="display: none"])');
    if (panel) rightClick(panel);
  })()`);
  await new Promise((r) => setTimeout(r, 200));
  check('a right click inside the open list does not close it', await eval_(`isOpen(0)`));

  // ── 3. A right click on another row closes it and opens the row menu ─────
  await eval_(`rightClick(rowCell(3))`);
  await waitFor(`! isOpen(0)`, { timeout: 2000 }).catch(() => {});
  check('a right click on another row closes the open select', ! (await eval_(`isOpen(0)`)));
  check('and opens that row\'s context menu', await eval_(`recordMenuOpen()`));
  // Past the leave transition (100 ms): the list must be gone from the screen,
  // not just flagged closed.
  await new Promise((r) => setTimeout(r, 400));
  check('no option list is left on screen beside the row menu', (await eval_(`listsDrawn()`)) === 0, `${await eval_(`listsDrawn()`)} drawn`);
  await shot('01-row-menu-only');
} catch (error) {
  check('driver ran without error', false, error.message);
} finally {
  await close();
}

finish({ consoleErrors, badResponses, shotDir });
